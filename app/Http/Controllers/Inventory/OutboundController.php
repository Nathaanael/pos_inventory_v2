<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\Feature;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\Outbound;
use App\Models\StoreSetting;
use App\Services\StockService;
use App\Services\ThermalPrintService;
use App\Support\Format;
use App\Support\StatusBadge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Barang Keluar. "Tambah" membuka layar POS (keranjang); transaksi langsung selesai
 * dan stok berkurang lewat StockService (sama seperti CreateOutboundPos di Filament).
 */
class OutboundController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $outbounds = Outbound::query()
            ->with('diningTable')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('transaction_code', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")))
            ->latest('date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('pages.inventory.outbounds.index', [
            'title' => 'Barang Keluar',
            'outbounds' => $outbounds,
            'showOrderType' => StoreSetting::hasFeature(Feature::DiningTables) || StoreSetting::hasFeature(Feature::TakeAway),
        ]);
    }

    /** Layar POS "Tambah Barang Keluar". */
    public function create(): View
    {
        return view('pages.inventory.outbounds.pos', [
            'title' => 'Tambah Barang Keluar',
            'code' => Outbound::generateTransactionCode('OUT'),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'hasWholesale' => StoreSetting::hasFeature(Feature::WholesalePrice),
        ]);
    }

    /**
     * Katalog barang untuk layar POS (JSON). Termasuk lapisan stok per supplier
     * supaya HPP FIFO bisa dihitung di browser saat jumlah diubah.
     */
    public function posItems(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q'));
        $categoryId = $request->integer('category') ?: null;
        $showImage = StoreSetting::hasFeature(Feature::ItemImage);

        $items = Item::query()
            ->where('is_active', true)
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->with(['unit', 'itemSuppliers' => fn ($q) => $q->where('current_stock', '>', 0)->orderBy('id')])
            ->orderBy('name')
            ->limit(60)
            ->get();

        return response()->json($items->map(fn (Item $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'code' => $item->code,
            'barcode' => $item->barcode,
            'unit' => $item->unit?->name ?? 'pcs',
            'stock' => (int) $item->current_stock,
            'min_stock' => (int) $item->min_stock,
            'image' => $showImage && $item->image ? Storage::disk('public')->url($item->image) : null,
            'price_retail' => (float) $item->harga_jual_retail,
            'price_wholesale' => (float) $item->harga_jual_grosir,
            'cost_fallback' => (float) $item->harga_beli,
            'cost_layers' => $item->itemSuppliers->map(fn ($s) => ['stock' => (int) $s->current_stock, 'price' => (float) $s->harga_beli])->values(),
        ]));
    }

    /**
     * Simpan transaksi dari POS (dipanggil lewat fetch, balasan JSON).
     * Harga jual & HPP dihitung ulang di server; browser hanya mengirim barang dan jumlah.
     */
    public function store(Request $request, StockService $stock): JsonResponse
    {
        $hasWholesale = StoreSetting::hasFeature(Feature::WholesalePrice);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'price_type_applied' => ['required', Rule::in(['retail', 'grosir'])],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'distinct', Rule::exists('items', 'id')],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
        ], [
            'lines.required' => 'Tambahkan minimal 1 barang sebelum menyimpan.',
            'lines.min' => 'Tambahkan minimal 1 barang sebelum menyimpan.',
        ]);

        $priceType = $hasWholesale ? $data['price_type_applied'] : 'retail';
        $items = Item::whereIn('id', collect($data['lines'])->pluck('item_id'))->get()->keyBy('id');

        $lines = collect($data['lines'])->map(function ($line) use ($items, $priceType) {
            $item = $items[$line['item_id']];
            $qty = (int) $line['quantity'];
            $price = (float) ($priceType === 'grosir' ? $item->harga_jual_grosir : $item->harga_jual_retail);

            return [
                'item_id' => $item->id,
                'quantity' => $qty,
                'sell_price' => $price,
                'cogs_price' => (float) $item->calculateFifoCogs($qty),
                'subtotal' => $price * $qty,
            ];
        })->all();

        try {
            $stock->createOutbound([
                'date' => $data['date'],
                'customer_name' => $data['customer_name'] ?? null,
                'destination' => $data['destination'] ?? null,
                'price_type_applied' => $priceType,
                'notes' => $data['notes'] ?? null,
            ], $lines);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        session()->flash('success', ['Berhasil ditambah', 'Transaksi barang keluar berhasil disimpan.']);

        return response()->json(['redirect' => route('outbounds.index')]);
    }

    public function show(Outbound $outbound): View
    {
        $outbound->load(['items.item', 'createdBy', 'diningTable']);
        [$status] = StatusBadge::for('outbound', $outbound->status);

        return view('pages.inventory.partials.detail', [
            'fields' => array_filter([
                'Kode Transaksi' => $outbound->transaction_code,
                'Tanggal' => Format::date($outbound->date),
                'Nama Pelanggan' => $outbound->customer_name,
                'Tujuan' => $outbound->destination,
                'Pesanan' => $outbound->orderLabel(),
                'Tipe Harga' => ucfirst((string) $outbound->price_type_applied),
                'Status' => $status,
                'Metode Bayar' => $outbound->payment_method ? (Outbound::PAYMENT_METHODS[$outbound->payment_method] ?? $outbound->payment_method) : null,
                'Total Nominal' => Format::rupiah($outbound->total_amount),
                'Total HPP' => Format::rupiah($outbound->total_cogs),
                'Dibuat Oleh' => $outbound->createdBy?->name,
                'Alasan Pembatalan' => $outbound->void_reason,
            ], fn ($v) => $v !== null),
            'notesLabel' => 'Catatan',
            'notes' => $outbound->notes,
            'columns' => ['Barang', 'Kuantitas', 'Harga Jual', 'HPP', 'Subtotal'],
            'rows' => $outbound->items->map(fn ($line) => [
                $line->item?->name ?? '-',
                Format::number($line->quantity),
                Format::rupiah($line->sell_price),
                Format::rupiah($line->cogs_price),
                Format::rupiah($line->subtotal),
            ])->all(),
            'footer' => [['label' => 'Total', 'value' => Format::rupiah($outbound->total_amount)]],
        ]);
    }

    /** Cetak nota langsung ke printer thermal (sama seperti aksi "Cetak Nota" di Filament). */
    public function printThermal(Outbound $outbound, ThermalPrintService $printer): RedirectResponse
    {
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

    public function void(Request $request, Outbound $outbound, StockService $stock): RedirectResponse
    {
        $data = $request->validate(['void_reason' => ['required', 'string']], [], ['void_reason' => 'Alasan Pembatalan']);

        if (! in_array($outbound->status, [TransactionStatus::COMPLETED, TransactionStatus::PENDING], true)) {
            return back()->with('error', ['Gagal', 'Transaksi ini tidak bisa dibatalkan.']);
        }

        $stock->voidOutbound($outbound, $data['void_reason']);

        return back()->with('success', 'Transaksi Berhasil Dibatalkan');
    }

    /**
     * Hapus massal. Transaksi Selesai tidak ikut dihapus karena stoknya sudah berkurang —
     * batalkan (void) dulu agar stok kembali.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $records = Outbound::whereIn('id', (array) $request->input('ids'))->get();
        $deletable = $records->reject(fn ($r) => $r->status === TransactionStatus::COMPLETED);

        $deletable->each->delete();
        $skipped = $records->count() - $deletable->count();

        return $skipped > 0
            ? back()->with('warning', ["{$deletable->count()} transaksi dihapus", "{$skipped} transaksi berstatus Selesai dilewati. Batalkan dulu agar stok dikembalikan."])
            : back()->with('success', 'Berhasil dihapus');
    }
}
