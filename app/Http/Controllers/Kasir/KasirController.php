<?php

namespace App\Http\Controllers\Kasir;

use App\Enums\Feature;
use App\Enums\OrderType;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Item;
use App\Models\Outbound;
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
        return view('pages.kasir.pos', [
            'title' => 'Kasir',
            'catalog' => $this->catalog(),
            'categories' => Category::orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all(),
            'recent' => $this->recentOrders(),
            'fnb' => $this->fnbOptions(),
            'tables' => $this->tableList(),
            // Dari halaman Meja: /kasir/pos?meja=ID langsung memilih meja itu.
            'preselectTable' => $request->integer('meja') ?: null,
            'hasWholesale' => StoreSetting::hasFeature(Feature::WholesalePrice),
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
            'price_type' => ['nullable', 'string'],
            'payment_method' => ['nullable', 'string'],
            'paid_amount' => ['nullable', 'numeric'],
            'customer_name' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'sale_id' => ['nullable', 'string'],
            'order_type' => ['nullable', 'string'],
            'table_id' => ['nullable', 'integer'],
        ]);

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

        return Item::query()
            ->where('is_active', true)
            ->with('unit:id,name')
            ->orderBy('name')
            ->get(['id', 'code', 'barcode', 'name', 'category_id', 'unit_id', 'harga_jual_retail', 'harga_jual_grosir', 'current_stock', 'image', 'tax_rate', 'tax_method'])
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'code' => $item->code,
                'barcode' => $item->barcode,
                'name' => $item->name,
                'category_id' => $item->category_id,
                'unit' => $item->unit?->name,
                'retail' => (float) $item->harga_jual_retail,
                'grosir' => (float) $item->harga_jual_grosir,
                'stock' => (int) $item->current_stock,
                'image' => $showImages && $item->image ? Storage::disk('public')->url($item->image) : null,
                // Pajak barang (null = ikut master). Hanya dipakai bila modul memakai pajak.
                'tax_rate' => $usesTaxes && $item->tax_rate !== null ? (float) $item->tax_rate : null,
                'tax_method' => $usesTaxes ? $item->tax_method : null,
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
            ->with(['activeOrders' => fn ($q) => $q->orderBy('created_at')->select(['id', 'dining_table_id', 'transaction_code', 'customer_name', 'total_amount', 'grand_total', 'created_at'])])
            ->orderByRaw('LENGTH(number), number')
            ->get()
            ->map(function (DiningTable $table) {
                $orders = $table->activeOrders;

                return [
                    'id' => $table->id,
                    'number' => $table->number,
                    'name' => $table->name,
                    'capacity' => $table->capacity,
                    'occupied' => $orders->isNotEmpty(),
                    'total' => (float) $orders->sum(fn (Outbound $o) => $o->payableTotal()),
                    // waktu mulai duduk (ISO) → lama duduk dihitung di browser
                    'since' => $orders->first()?->created_at?->toIso8601String(),
                    'customer' => $orders->pluck('customer_name')->filter()->unique()->implode(', '),
                    'orders' => $orders->map(fn (Outbound $o) => [
                        'code' => $o->transaction_code,
                        'customer' => $o->customer_name,
                        'total' => $o->payableTotal(),
                        'time' => $o->created_at->format('H:i'),
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
        $paymentMethod = (string) ($data['payment_method'] ?? '');

        if (! array_key_exists($paymentMethod, Outbound::PAYMENT_METHODS)) {
            return ['ok' => false, 'message' => 'Pilih metode pembayaran.'];
        }

        // Gabungkan baris dengan barang yang sama & buang jumlah tidak valid.
        $quantities = [];
        foreach ($data['lines'] ?? [] as $line) {
            $id = (int) ($line['id'] ?? 0);
            $qty = (int) ($line['qty'] ?? 0);
            if ($id > 0 && $qty > 0) {
                $quantities[$id] = ($quantities[$id] ?? 0) + $qty;
            }
        }

        if (! $quantities) {
            return ['ok' => false, 'message' => 'Keranjang masih kosong.'];
        }

        $items = Item::query()->where('is_active', true)->whereIn('id', array_keys($quantities))->get()->keyBy('id');
        $lines = [];
        $taxLines = [];

        foreach ($quantities as $id => $qty) {
            $item = $items->get($id);
            if (! $item) {
                return ['ok' => false, 'message' => 'Ada barang yang sudah tidak aktif. Muat ulang halaman.'];
            }
            if ($item->current_stock < $qty) {
                return ['ok' => false, 'message' => "Stok {$item->name} tidak cukup. Tersedia: {$item->current_stock}."];
            }

            // Harga grosir dipakai bila diisi; jika kosong (0) pakai harga eceran.
            $price = $priceType === 'grosir' && (float) $item->harga_jual_grosir > 0
                ? (float) $item->harga_jual_grosir
                : (float) $item->harga_jual_retail;

            $lines[] = [
                'item_id' => $item->id,
                'quantity' => $qty,
                'sell_price' => $price,
                'cogs_price' => (float) $item->calculateFifoCogs($qty),
                'subtotal' => $price * $qty,
            ];
            $taxLines[] = ['subtotal' => $price * $qty, 'tax_rate' => $item->tax_rate, 'tax_method' => $item->tax_method];
        }

        // Pajak & biaya layanan (modul FNB). Yang dibayar = grand_total.
        $taxes = app(TaxCalculator::class)->calculate($taxLines, $fnb['order_type']);
        $total = $taxes['grand_total'];
        $paidAmount = isset($data['paid_amount']) ? (float) $data['paid_amount'] : null;

        if ($paymentMethod === 'tunai') {
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
                'service_amount' => $taxes['service_amount'],
                'tax_amount' => $taxes['tax_amount'],
                'grand_total' => $taxes['grand_total'],
                'tax_details' => $taxes['details'] ?: null,
            ], $lines);
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => 'Gagal menyimpan: '.$e->getMessage()];
        }

        return [
            'ok' => true,
            'message' => "Transaksi {$outbound->transaction_code} tersimpan.",
            'code' => $outbound->transaction_code,
            'catalog' => $this->catalog(),
            'recent' => $this->recentOrders(),
            'tables' => $this->tableList(),
        ];
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
}
