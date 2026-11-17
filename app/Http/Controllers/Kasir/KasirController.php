<?php

namespace App\Http\Controllers\Kasir;

use App\Enums\Feature;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Item;
use App\Models\Modifier;
use App\Models\Outbound;
use App\Models\OutboundItem;
use App\Models\StoreSetting;
use App\Services\StockService;
use App\Services\TaxCalculator;
use App\Services\ThermalPrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Halaman kasir (dulu panel Filament "kasir"): layar POS, pantauan meja, riwayat transaksi.
 *
 * Keranjang dikelola sepenuhnya di browser (Alpine.js) supaya klik barang langsung masuk
 * tanpa menunggu server. Server hanya dipanggil saat checkout, dan di sana harga & stok
 * dihitung ulang dari database, jadi data dari browser tidak dipercaya begitu saja.
 */
class KasirController extends Controller
{
    /* ============================== Layar POS ============================== */

    public function pos(Request $request): View
    {
        $activeOrder = null;
        if ($orderId = $request->integer('order')) {
            $order = Outbound::with(['items.item:id,name', 'diningTable:id,number,name'])
                ->where('status', TransactionStatus::OPEN->value)
                ->find($orderId);

            if ($order) {
                $activeOrder = [
                    'id' => $order->id,
                    'code' => $order->transaction_code,
                    'customer_name' => $order->customer_name,
                    'order_type' => $order->order_type?->value ?? 'dine_in',
                    'table_id' => $order->dining_table_id,
                    'table_label' => $order->orderLabel(),
                    'total' => $order->payableTotal(),
                    'items_count' => $order->items->sum('quantity'),
                ];
            }
        }

        return view('pages.kasir.pos', [
            'title' => 'Kasir',
            'catalog' => $this->catalog(),
            'categories' => Category::orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all(),
            'recent' => $this->recentOrders(),
            'fnb' => $this->fnbOptions(),
            'tables' => $this->tableList(),
            // Dari halaman Meja: /kasir/pos?meja=ID langsung memilih meja itu.
            'preselectTable' => $activeOrder['table_id'] ?? ($request->integer('meja') ?: null),
            'activeOrder' => $activeOrder,
            'hasWholesale' => StoreSetting::hasFeature(Feature::WholesalePrice),
            'usesModifiers' => StoreSetting::usesModifiers(),
            'activeShift' => $activeShift = \App\Models\CashierShift::activeFor($request->user()?->id),
            // Modul FNB: transaksi wajib di dalam shift kasir (agar masuk Hitung Kas).
            'shiftRequired' => StoreSetting::usesCashCount() && ! $activeShift,
            'taxRules' => app(TaxCalculator::class)->rules(),
        ]);
    }

    /**
     * Simpan transaksi (fetch dari layar POS).
     * Balasan: { ok, message, code?, catalog?, recent?, tables? }
     */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lines' => ['array'],
            'lines.*.id' => ['integer'],
            'lines.*.qty' => ['integer'],
            'lines.*.modifier_ids' => ['nullable', 'array'],
            'lines.*.modifier_ids.*' => ['integer'],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
            'price_type' => ['nullable', 'string'],
            'payment_method' => ['nullable', 'string'],
            'paid_amount' => ['nullable', 'numeric'],
            'customer_name' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'sale_id' => ['nullable', 'string'],
            'order_type' => ['nullable', 'string'],
            'table_id' => ['nullable', 'integer'],
            'save_as_open_bill' => ['nullable', 'boolean'],
            'discount_type' => ['nullable', 'in:percent,fixed'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'discount_reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($blocked = $this->requireShift($request)) {
            return $blocked;
        }

        // Pengaman transaksi dobel: setiap penjualan di browser punya sale_id unik.
        // Kalau id yang sama dikirim lagi (klik ganda, koneksi ulang, tab ganda), tolak.
        $saleId = $data['sale_id'] ?? null;
        $lockKey = $saleId ? 'kasir-checkout:'.$request->user()->id.':'.mb_substr($saleId, 0, 64) : null;

        if ($lockKey && ! Cache::add($lockKey, 'processing', now()->addMinutes(30))) {
            return response()->json(['ok' => false, 'duplicate' => true, 'message' => 'Transaksi ini sudah dikirim. Cek Riwayat Transaksi sebelum menyimpan ulang.']);
        }

        $result = $this->processCheckout($data);

        // Gagal validasi (mis. stok kurang): izinkan kasir memperbaiki lalu kirim ulang id yang sama.
        if ($lockKey && ! $result['ok']) {
            Cache::forget($lockKey);
        }

        return response()->json($result);
    }

    /**
     * Tambahkan item baru ke Open Bill yang sudah ada.
     */
    public function addOrderItems(Request $request, Outbound $outbound): JsonResponse
    {
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['integer'],
            'lines.*.qty' => ['integer'],
            'lines.*.modifier_ids' => ['nullable', 'array'],
            'lines.*.modifier_ids.*' => ['integer'],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
            'price_type' => ['nullable', 'string'],
        ]);

        if ($outbound->status !== TransactionStatus::OPEN) {
            return response()->json(['ok' => false, 'message' => 'Pesanan ini bukan Open Bill atau sudah dibayar.']);
        }

        $priceType = ($data['price_type'] ?? null) === 'grosir' && StoreSetting::hasFeature(Feature::WholesalePrice) ? 'grosir' : 'retail';
        $built = $this->buildValidatedLines($data['lines'], $priceType);
        if (is_string($built)) {
            return response()->json(['ok' => false, 'message' => $built]);
        }

        try {
            $outbound = app(StockService::class)->addItemsToOutbound($outbound, $built['lines']);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => 'Gagal menambah pesanan: '.$e->getMessage()]);
        }

        return response()->json([
            'ok' => true,
            'message' => "Pesanan tambahan masuk ke {$outbound->transaction_code}.",
            'code' => $outbound->transaction_code,
            'catalog' => $this->catalog(),
            'recent' => $this->recentOrders(),
            'tables' => $this->tableList(),
        ]);
    }

    /**
     * Bayar Open Bill (mendukung single atau split payment).
     */
    public function payOrder(Request $request, Outbound $outbound): JsonResponse
    {
        $data = $request->validate([
            'payment_method' => ['nullable', 'string'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['nullable', 'array'],
            'payments.*.payment_method' => ['required_with:payments', 'string'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:1'],
        ]);

        if ($blocked = $this->requireShift($request)) {
            return $blocked;
        }

        if ($outbound->status !== TransactionStatus::OPEN) {
            return response()->json(['ok' => false, 'message' => 'Pesanan ini sudah dibayar atau dibatalkan.']);
        }

        $remaining = $outbound->remainingBalance();
        $payments = [];
        $cashReceived = null;

        if (! empty($data['payments'])) {
            foreach ($data['payments'] as $p) {
                if (! array_key_exists($p['payment_method'], Outbound::PAYMENT_METHODS)) {
                    return response()->json(['ok' => false, 'message' => 'Metode pembayaran tidak valid.']);
                }
                $payments[] = [
                    'payment_method' => $p['payment_method'],
                    'amount' => (float) $p['amount'],
                ];
            }
            if (collect($payments)->sum('amount') + 0.01 < $remaining) {
                return response()->json(['ok' => false, 'message' => 'Total pembayaran kurang dari sisa tagihan.']);
            }
        } else {
            $method = (string) ($data['payment_method'] ?? '');
            if (! array_key_exists($method, Outbound::PAYMENT_METHODS)) {
                return response()->json(['ok' => false, 'message' => 'Pilih metode pembayaran.']);
            }
            $paid = isset($data['paid_amount']) ? (float) $data['paid_amount'] : $remaining;
            if ($method === 'tunai' && $paid < $remaining) {
                return response()->json(['ok' => false, 'message' => 'Uang diterima kurang dari total tagihan.']);
            }
            $cashReceived = $paid;
            $payments[] = [
                'payment_method' => $method,
                'amount' => $remaining,
            ];
        }

        try {
            $outbound = app(StockService::class)->payOutbound($outbound, $payments, $cashReceived);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => 'Gagal memproses pembayaran: '.$e->getMessage()]);
        }

        return response()->json([
            'ok' => true,
            'message' => "Tagihan {$outbound->transaction_code} lunas.",
            'tables' => $this->tablesWithOrders(),
        ]);
    }

    /**
     * Batalkan satu baris item dari Open Bill.
     */
    public function voidOrderItem(Request $request, Outbound $outbound, OutboundItem $outboundItem): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            app(StockService::class)->voidOutboundItem($outbound, $outboundItem, $data['reason']);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Item berhasil dibatalkan.',
            'tables' => $this->tablesWithOrders(),
        ]);
    }

    /**
     * Cetak Tiket Dapur (KOT) ke printer thermal.
     */
    public function printKot(Outbound $outbound, ThermalPrintService $printer): RedirectResponse
    {
        try {
            $outbound->load(['items.item', 'diningTable']);
            $printer->printKitchenTicket($outbound);
        } catch (Throwable $e) {
            return back()->with('error', ['Gagal cetak tiket dapur', $e->getMessage()]);
        }

        return back()->with('success', 'Tiket dapur (KOT) berhasil dicetak!');
    }

    /* ============================== Meja ============================== */

    public function meja(): View
    {
        return view('pages.kasir.meja', [
            'title' => 'Meja',
            'tables' => $this->tablesWithOrders(),
        ]);
    }

    /** Data meja terbaru (dipanggil berkala tiap 20 detik, pengganti wire:poll). */
    public function mejaData(): JsonResponse
    {
        return response()->json($this->tablesWithOrders());
    }

    /** Pelanggan sudah selesai: meja jadi kosong kembali. */
    public function release(DiningTable $table): RedirectResponse
    {
        $table->release();

        return back()->with('success', "Meja {$table->number} dikosongkan");
    }

    /** Pindahkan semua pesanan aktif dari meja ini ke meja tujuan yang kosong. */
    public function moveTable(Request $request, DiningTable $table): RedirectResponse
    {
        $data = $request->validate([
            'target_table_id' => ['required', 'integer', 'different:'.$table->id],
        ]);

        $target = DiningTable::active()->withCount('activeOrders')->find($data['target_table_id']);
        if (! $target) {
            return back()->with('error', ['Gagal pindah meja', 'Meja tujuan tidak ditemukan atau nonaktif.']);
        }
        if ($target->active_orders_count > 0) {
            return back()->with('error', ['Gagal pindah meja', "Meja {$target->number} sedang terisi."]);
        }

        $moved = $table->activeOrders()->update(['dining_table_id' => $target->id]);
        if ($moved === 0) {
            return back()->with('error', ['Tidak ada pesanan aktif', "Meja {$table->number} sudah kosong."]);
        }

        return back()->with('success', "Pesanan dipindahkan dari Meja {$table->number} ke Meja {$target->number}.");
    }

    /* ============================== Riwayat ============================== */

    public function riwayat(Request $request): View
    {
        // Filter "Hanya hari ini" aktif secara default (sama seperti versi Filament).
        $todayOnly = $request->query('hari_ini', '1') === '1';
        $search = trim((string) $request->query('q'));

        $orders = Outbound::query()
            ->where('created_by', $request->user()->id)
            ->with('diningTable')
            ->withCount('items')
            ->when($todayOnly, fn ($q) => $q->whereDate('created_at', today()))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('transaction_code', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")))
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('pages.kasir.riwayat', [
            'title' => 'Riwayat Transaksi Saya',
            'orders' => $orders,
            'todayOnly' => $todayOnly,
            'showOrderType' => StoreSetting::hasFeature(Feature::DiningTables) || StoreSetting::hasFeature(Feature::TakeAway),
        ]);
    }

    /** Cetak nota ke printer thermal (hanya transaksi milik kasir ini). */
    public function printThermal(Request $request, Outbound $outbound, ThermalPrintService $printer): RedirectResponse
    {
        abort_unless((int) $outbound->created_by === (int) $request->user()->id, 403);

        if ($outbound->status !== TransactionStatus::COMPLETED) {
            return back()->with('error', ['Gagal cetak nota', 'Nota hanya bisa dicetak untuk transaksi yang sudah selesai.']);
        }

        try {
            $outbound->load(['items.item.unit', 'createdBy']);
            $printer->printOutboundReceipt($outbound);
        } catch (Throwable $e) {
            return back()->with('error', ['Gagal cetak nota', $e->getMessage()]);
        }

        return back()->with('success', 'Nota berhasil dicetak!');
    }

    /* ============================== Toko belum siap ============================== */

    public function notReady(): View|RedirectResponse
    {
        StoreSetting::flushCache();

        // Setup sudah selesai: langsung ke layar kasir.
        if (StoreSetting::current()->isSetUp()) {
            return redirect()->route('kasir.pos');
        }

        return view('pages.kasir.not-ready', ['title' => 'Toko belum disiapkan']);
    }

    /* ============================== Data ============================== */

    /**
     * Seluruh barang aktif, dikirim sekali ke browser.
     *
     * @return array<int, array<string, mixed>>
     */
    private function catalog(): array
    {
        $showImages = StoreSetting::hasFeature(Feature::ItemImage);
        $usesTaxes = StoreSetting::usesTaxes();
        $usesModifiers = StoreSetting::usesModifiers();
        $usesRecipes = StoreSetting::usesRecipes();

        $with = ['unit:id,name'];
        if ($usesModifiers) {
            $with[] = 'modifierGroups.activeModifiers';
        }
        if ($usesRecipes) {
            $with[] = 'recipe.ingredients.ingredientItem:id,unit_id,current_stock,harga_beli';
        }

        return Item::query()
            ->where('is_active', true)
            ->where('item_type', '!=', Item::TYPE_INGREDIENT)
            ->with($with)
            ->orderBy('name')
            ->get(['id', 'code', 'barcode', 'name', 'category_id', 'unit_id', 'item_type', 'track_stock', 'harga_jual_retail', 'harga_jual_grosir', 'current_stock', 'image', 'tax_rate', 'tax_method'])
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'code' => $item->code,
                'barcode' => $item->barcode,
                'name' => $item->name,
                'category_id' => $item->category_id,
                'unit' => $item->unit?->name,
                'retail' => (float) $item->harga_jual_retail,
                'grosir' => (float) $item->harga_jual_grosir,
                'stock' => $item->usesRecipeStock() ? $item->availablePortions() : (int) floor((float) $item->current_stock),
                'image' => $showImages && $item->image ? Storage::disk('public')->url($item->image) : null,
                // Pajak barang (null = ikut master). Hanya dipakai bila modul memakai pajak.
                'tax_rate' => $usesTaxes && $item->tax_rate !== null ? (float) $item->tax_rate : null,
                'tax_method' => $usesTaxes ? $item->tax_method : null,
                'modifier_groups' => $usesModifiers
                    ? $item->modifierGroups->where('is_active', true)->map->toPosGroup()->values()->all()
                    : [],
            ])
            ->all();
    }

    /**
     * Transaksi terakhir kasir ini hari ini (strip di bawah daftar barang).
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentOrders(): array
    {
        return Outbound::query()
            ->where('created_by', auth()->id())
            ->whereDate('created_at', today())
            ->withCount('items')
            ->with('diningTable:id,number')
            ->latest('id')
            ->limit(8)
            ->get(['id', 'transaction_code', 'customer_name', 'status', 'total_amount', 'grand_total', 'created_at', 'order_type', 'dining_table_id'])
            ->map(fn (Outbound $o) => [
                'id' => $o->id,
                'no' => Str::afterLast($o->transaction_code, '-'),
                'customer' => $o->customer_name ?: 'Pelanggan umum',
                'order' => $o->orderLabel(),
                'items' => $o->items_count,
                'total' => $o->payableTotal(),
                'time' => $o->created_at->format('H:i'),
                'status' => $o->status->value,
                'print_url' => $o->status === TransactionStatus::COMPLETED ? route('outbound.print', $o) : null,
            ])
            ->all();
    }

    /** @return array{tables: bool, takeAway: bool} */
    private function fnbOptions(): array
    {
        return [
            'tables' => StoreSetting::hasFeature(Feature::DiningTables),
            'takeAway' => StoreSetting::hasFeature(Feature::TakeAway),
        ];
    }

    /**
     * Meja aktif untuk pilihan nomor meja (kosong bila fitur meja nonaktif).
     *
     * @return array<int, array{id: int, number: string, name: ?string, occupied: bool}>
     */
    private function tableList(): array
    {
        if (! StoreSetting::hasFeature(Feature::DiningTables)) {
            return [];
        }

        return DiningTable::query()
            ->active()
            ->withCount('activeOrders')
            ->orderByRaw('LENGTH(number), number')
            ->get()
            ->map(fn (DiningTable $t) => [
                'id' => $t->id,
                'number' => $t->number,
                'name' => $t->name,
                'occupied' => $t->active_orders_count > 0,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function tablesWithOrders(): array
    {
        return DiningTable::query()
            ->active()
            ->with(['activeOrders' => fn ($q) => $q
                ->with(['items.item:id,name'])
                ->orderBy('created_at')
                ->select(['id', 'dining_table_id', 'transaction_code', 'customer_name', 'status', 'payment_status', 'total_amount', 'grand_total', 'created_at'])])
            ->orderByRaw('LENGTH(number), number')
            ->get()
            ->map(function (DiningTable $table) {
                $orders = $table->activeOrders;
                $hasOpenBill = $orders->contains(fn (Outbound $o) => $o->status === TransactionStatus::OPEN);

                return [
                    'id' => $table->id,
                    'number' => $table->number,
                    'name' => $table->name,
                    'capacity' => $table->capacity,
                    'occupied' => $orders->isNotEmpty(),
                    'has_open_bill' => $hasOpenBill,
                    'total' => (float) $orders->sum(fn (Outbound $o) => $o->payableTotal()),
                    // waktu mulai duduk (ISO) → lama duduk dihitung di browser
                    'since' => $orders->first()?->created_at?->toIso8601String(),
                    'customer' => $orders->pluck('customer_name')->filter()->unique()->implode(', '),
                    'orders' => $orders->map(fn (Outbound $o) => [
                        'id' => $o->id,
                        'code' => $o->transaction_code,
                        'customer' => $o->customer_name,
                        'status' => $o->status->value,
                        'is_open' => $o->status === TransactionStatus::OPEN,
                        'payment_status' => $o->payment_status?->value ?? PaymentStatus::PAID->value,
                        'total' => $o->payableTotal(),
                        'remaining' => $o->remainingBalance(),
                        'time' => $o->created_at->format('H:i'),
                        'items' => $o->items->map(fn (OutboundItem $l) => [
                            'id' => $l->id,
                            'name' => $l->item?->name ?? 'Item',
                            'qty' => (int) $l->quantity,
                            'subtotal' => (float) $l->subtotal,
                            'modifiers' => collect($l->modifiers_snapshot ?? [])->pluck('name')->all(),
                            'notes' => $l->notes,
                        ])->values()->all(),
                    ])->values()->all(),
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function processCheckout(array $data): array
    {
        $fnb = $this->resolveOrderType($data['order_type'] ?? null, $data['table_id'] ?? null);
        if (is_string($fnb)) {
            return ['ok' => false, 'message' => $fnb];
        }

        // Harga grosir hanya bila fiturnya aktif (Pengaturan Toko → Fitur).
        $priceType = ($data['price_type'] ?? null) === 'grosir' && StoreSetting::hasFeature(Feature::WholesalePrice) ? 'grosir' : 'retail';
        $isOpenBill = (bool) ($data['save_as_open_bill'] ?? false) && StoreSetting::usesModifiers();
        $paymentMethod = (string) ($data['payment_method'] ?? '');

        if (! $isOpenBill && ! array_key_exists($paymentMethod, Outbound::PAYMENT_METHODS)) {
            return ['ok' => false, 'message' => 'Pilih metode pembayaran.'];
        }

        $built = $this->buildValidatedLines($data['lines'] ?? [], $priceType);
        if (is_string($built)) {
            return ['ok' => false, 'message' => $built];
        }

        $rawSubtotal = (float) collect($built['lines'])->sum('subtotal');
        $discountType = $data['discount_type'] ?? null;
        $discountValue = (float) ($data['discount_value'] ?? 0);
        $discountAmount = 0.0;
        if ($discountType === 'percent' && $discountValue > 0) {
            $discountAmount = round($rawSubtotal * min(100, $discountValue) / 100);
        } elseif ($discountType === 'fixed' && $discountValue > 0) {
            $discountAmount = min($rawSubtotal, round($discountValue));
        } else {
            $discountType = null;
            $discountValue = 0;
        }

        // Pajak & biaya layanan (modul FNB). Yang dibayar = grand_total.
        $taxes = app(TaxCalculator::class)->calculate($built['tax_lines'], $fnb['order_type'], null, $discountAmount);
        $total = $taxes['grand_total'];
        $paidAmount = isset($data['paid_amount']) ? (float) $data['paid_amount'] : null;

        if ($isOpenBill) {
            $paymentMethod = null;
            $paidAmount = 0;
        } elseif ($paymentMethod === 'tunai') {
            if ($paidAmount === null || $paidAmount < $total) {
                return ['ok' => false, 'message' => 'Uang diterima kurang dari total belanja.'];
            }
        } else {
            $paidAmount = $total;
        }

        try {
            $outbound = app(StockService::class)->createOutbound([
                'date' => now()->toDateString(),
                'customer_name' => filled($data['customer_name'] ?? null) ? mb_substr(trim($data['customer_name']), 0, 100) : null,
                'price_type_applied' => $priceType,
                'payment_method' => $paymentMethod,
                'paid_amount' => $paidAmount,
                'notes' => filled($data['notes'] ?? null) ? mb_substr(trim($data['notes']), 0, 500) : null,
                'order_type' => $fnb['order_type'],
                'dining_table_id' => $fnb['dining_table_id'],
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $discountAmount,
                'discount_reason' => filled($data['discount_reason'] ?? null) ? mb_substr(trim($data['discount_reason']), 0, 255) : null,
                'service_amount' => $taxes['service_amount'],
                'tax_amount' => $taxes['tax_amount'],
                'grand_total' => $taxes['grand_total'],
                'tax_details' => $taxes['details'] ?: null,
            ], $built['lines'], $isOpenBill);
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => 'Gagal menyimpan: '.$e->getMessage()];
        }

        return [
            'ok' => true,
            'is_open_bill' => $isOpenBill,
            'message' => $isOpenBill
                ? "Open Bill {$outbound->transaction_code} tersimpan."
                : "Transaksi {$outbound->transaction_code} tersimpan.",
            'code' => $outbound->transaction_code,
            'outbound_id' => $outbound->id,
            'catalog' => $this->catalog(),
            'recent' => $this->recentOrders(),
            'tables' => $this->tableList(),
        ];
    }

    /**
     * Validasi baris keranjang, cek total stok per item, dan hitung harga modifier dari database.
     *
     * @param  array<int, array<string, mixed>>  $rawLines
     * @return array{lines: array<int, array<string, mixed>>, tax_lines: array<int, array<string, mixed>>}|string
     */
    private function buildValidatedLines(array $rawLines, string $priceType): array|string
    {
        $usesModifiers = StoreSetting::usesModifiers();
        $grouped = [];
        $totalQtyByItem = [];

        foreach ($rawLines as $line) {
            $id = (int) ($line['id'] ?? 0);
            $qty = (int) ($line['qty'] ?? 0);
            if ($id <= 0 || $qty <= 0) {
                continue;
            }

            $modIds = $usesModifiers
                ? collect($line['modifier_ids'] ?? [])->map(fn ($m) => (int) $m)->filter()->unique()->sort()->values()->all()
                : [];
            $notes = $usesModifiers && filled($line['notes'] ?? null)
                ? mb_substr(trim((string) $line['notes']), 0, 255)
                : null;

            $sig = $id.'|'.implode(',', $modIds).'|'.($notes ?? '');
            if (! isset($grouped[$sig])) {
                $grouped[$sig] = ['id' => $id, 'qty' => 0, 'modifier_ids' => $modIds, 'notes' => $notes];
            }
            $grouped[$sig]['qty'] += $qty;
            $totalQtyByItem[$id] = ($totalQtyByItem[$id] ?? 0) + $qty;
        }

        if (! $grouped) {
            return 'Keranjang masih kosong.';
        }

        $itemsQuery = Item::query()->where('is_active', true)->whereIn('id', array_keys($totalQtyByItem));
        if ($usesModifiers) {
            $itemsQuery->with('modifierGroups.activeModifiers');
        }
        if (StoreSetting::usesRecipes()) {
            $itemsQuery->with('recipe.ingredients.ingredientItem');
        }
        $items = $itemsQuery->get()->keyBy('id');

        foreach ($totalQtyByItem as $id => $totalQty) {
            $item = $items->get($id);
            if (! $item) {
                return 'Ada barang yang sudah tidak aktif. Muat ulang halaman.';
            }
            $available = $item->usesRecipeStock() ? $item->availablePortions() : (float) $item->current_stock;
            if ($available + 0.0001 < $totalQty) {
                return "Stok {$item->name} tidak cukup. Tersedia: {$available}.";
            }
        }

        $lines = [];
        $taxLines = [];

        foreach ($grouped as $entry) {
            /** @var Item $item */
            $item = $items->get($entry['id']);
            $qty = $entry['qty'];

            $modifiersSnapshot = [];
            $modifiersTotal = 0.0;

            if ($usesModifiers && ! empty($entry['modifier_ids'])) {
                $allowedMods = $item->modifierGroups
                    ->where('is_active', true)
                    ->flatMap(fn ($g) => $g->activeModifiers)
                    ->keyBy('id');

                foreach ($entry['modifier_ids'] as $modId) {
                    /** @var Modifier|null $mod */
                    $mod = $allowedMods->get($modId);
                    if ($mod) {
                        $modifiersSnapshot[] = [
                            'id' => $mod->id,
                            'name' => $mod->name,
                            'price_delta' => (float) $mod->price_delta,
                        ];
                        $modifiersTotal += (float) $mod->price_delta;
                    }
                }
            }

            // Validasi grup modifier wajib.
            if ($usesModifiers) {
                foreach ($item->modifierGroups->where('is_active', true) as $group) {
                    $groupModIds = $group->activeModifiers->pluck('id')->all();
                    $picked = count(array_intersect($entry['modifier_ids'], $groupModIds));
                    $min = $group->is_required ? max(1, $group->min_select) : $group->min_select;
                    if ($picked < $min) {
                        return "Pilih minimal {$min} opsi pada {$group->name} untuk {$item->name}.";
                    }
                    if ($picked > $group->max_select) {
                        return "Maksimal {$group->max_select} opsi pada {$group->name} untuk {$item->name}.";
                    }
                }
            }

            $price = $priceType === 'grosir' && (float) $item->harga_jual_grosir > 0
                ? (float) $item->harga_jual_grosir
                : (float) $item->harga_jual_retail;

            $subtotal = ($price + $modifiersTotal) * $qty;

            $lines[] = [
                'item_id' => $item->id,
                'quantity' => $qty,
                'sell_price' => $price,
                'cogs_price' => (float) $item->calculateFifoCogs($qty),
                'subtotal' => $subtotal,
                'modifiers_snapshot' => $modifiersSnapshot ?: null,
                'modifiers_total' => $modifiersTotal,
                'notes' => $entry['notes'],
            ];
            $taxLines[] = ['subtotal' => $subtotal, 'tax_rate' => $item->tax_rate, 'tax_method' => $item->tax_method];
        }

        return ['lines' => $lines, 'tax_lines' => $taxLines];
    }

    /**
     * Tipe pesanan & meja sesuai fitur FNB yang aktif.
     *  - Fitur Meja & Take Away nonaktif : order_type & meja dikosongkan.
     *  - Take Away                       : tanpa meja.
     *  - Makan di tempat + fitur Meja    : meja wajib dipilih dan harus aktif.
     *
     * @return array{order_type: ?string, dining_table_id: ?int}|string  string = pesan error
     */
    private function resolveOrderType(?string $orderType, ?int $tableId): array|string
    {
        $hasTables = StoreSetting::hasFeature(Feature::DiningTables);
        $hasTakeAway = StoreSetting::hasFeature(Feature::TakeAway);

        if (! $hasTables && ! $hasTakeAway) {
            return ['order_type' => null, 'dining_table_id' => null];
        }

        if ($hasTakeAway && $orderType === OrderType::TakeAway->value) {
            return ['order_type' => OrderType::TakeAway->value, 'dining_table_id' => null];
        }

        if (! $hasTables) {
            return ['order_type' => OrderType::DineIn->value, 'dining_table_id' => null];
        }

        if (! $tableId) {
            return 'Pilih nomor meja terlebih dahulu.';
        }

        if (! DiningTable::query()->active()->whereKey($tableId)->exists()) {
            return 'Meja tidak ditemukan atau sudah nonaktif. Muat ulang halaman.';
        }

        return ['order_type' => OrderType::DineIn->value, 'dining_table_id' => $tableId];
    }

    /**
     * Modul FNB: checkout & pembayaran hanya bisa dilakukan saat pengguna punya shift kasir aktif,
     * supaya setiap uang masuk tercatat di shift dan ikut dihitung di Hitung Kas.
     */
    private function requireShift(Request $request): ?JsonResponse
    {
        if (! StoreSetting::usesCashCount() || \App\Models\CashierShift::activeFor($request->user()?->id)) {
            return null;
        }

        return response()->json([
            'ok' => false,
            'shift_required' => true,
            'message' => 'Buka shift kasir terlebih dahulu sebelum menyimpan transaksi.',
        ]);
    }
}
