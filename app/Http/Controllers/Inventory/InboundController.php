<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Inbound;
use App\Models\Item;
use App\Models\ItemSupplier;
use App\Models\StoreSetting;
use App\Models\Supplier;
use App\Services\StockService;
use App\Support\Format;
use App\Support\ItemCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Barang Masuk. Dibuat berstatus "pending"; stok baru bertambah saat disetujui.
 */
class InboundController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $inbounds = Inbound::query()
            ->with('supplier')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('transaction_code', 'like', "%{$search}%")
                ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%"))))
            ->latest('date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('pages.inventory.inbounds.index', [
            'title' => 'Barang Masuk',
            'inbounds' => $inbounds,
            'usesSuppliers' => StoreSetting::usesSuppliers(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Inbound(['date' => now(), 'status' => TransactionStatus::PENDING]));
    }

    public function store(Request $request, StockService $stock): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
            $inbound = Inbound::create([
                ...$data['header'],
                'status' => TransactionStatus::PENDING,
                'created_by' => $request->user()->id,
                'total_amount' => collect($data['lines'])->sum(fn ($l) => $l['buy_price'] * $l['quantity']),
            ]);

            foreach ($data['lines'] as $line) {
                $inbound->items()->create($line);
            }
        });

        return $this->redirectAfterSave($request, 'Berhasil ditambah');
    }

    public function edit(Inbound $inbound): View|RedirectResponse
    {
        if ($inbound->status !== TransactionStatus::PENDING) {
            return redirect()->route('inbounds.index')->with('error', ['Tidak bisa diubah', 'Hanya transaksi berstatus Pending yang bisa diubah.']);
        }

        return $this->form($inbound->load('items'));
    }

    public function update(Request $request, Inbound $inbound): RedirectResponse
    {
        abort_unless($inbound->status === TransactionStatus::PENDING, 403, 'Hanya transaksi berstatus Pending yang bisa diubah.');

        $data = $this->validated($request);

        DB::transaction(function () use ($data, $inbound) {
            $inbound->update([
                ...$data['header'],
                'total_amount' => collect($data['lines'])->sum(fn ($l) => $l['buy_price'] * $l['quantity']),
            ]);

            // Status masih pending → baris boleh diganti seluruhnya (stok belum tersentuh).
            $inbound->items()->delete();
            foreach ($data['lines'] as $line) {
                $inbound->items()->create($line);
            }
        });

        return redirect()->route('inbounds.index')->with('success', 'Berhasil diperbarui');
    }

    public function show(Inbound $inbound): View
    {
        $inbound->load(['supplier', 'items.item', 'createdBy']);
        [$status] = \App\Support\StatusBadge::for('inbound', $inbound->status);

        return view('pages.inventory.partials.detail', [
            'fields' => [
                'Kode Transaksi' => $inbound->transaction_code,
                'Tanggal' => Format::date($inbound->date),
                'Supplier' => $inbound->supplier?->name,
                'Status' => $status,
                'No. Invoice Supplier' => $inbound->supplier_invoice_no,
                'No. Surat Jalan' => $inbound->delivery_note_no,
                'Total Nominal' => Format::rupiah($inbound->total_amount),
                'Dibuat Oleh' => $inbound->createdBy?->name,
                'Alasan Pembatalan' => $inbound->void_reason,
            ],
            'notesLabel' => 'Catatan',
            'notes' => $inbound->notes,
            'columns' => ['Barang', 'Kuantitas', 'Harga Beli', 'Subtotal'],
            'rows' => $inbound->items->map(fn ($line) => [
                $line->item?->name ?? '-',
                Format::number($line->quantity),
                Format::rupiah($line->buy_price),
                Format::rupiah($line->subtotal),
            ])->all(),
            'footer' => [['label' => 'Total', 'value' => Format::rupiah($inbound->total_amount)]],
        ]);
    }

    public function approve(Inbound $inbound, StockService $stock): RedirectResponse
    {
        if ($inbound->status !== TransactionStatus::PENDING) {
            return back()->with('error', ['Gagal', 'Transaksi ini sudah diproses.']);
        }

        $stock->approveInbound($inbound);

        return back()->with('success', 'Transaksi Berhasil Disetujui');
    }

    public function void(Request $request, Inbound $inbound, StockService $stock): RedirectResponse
    {
        $data = $request->validate(['void_reason' => ['required', 'string']], [], ['void_reason' => 'Alasan Pembatalan']);

        if ($inbound->status !== TransactionStatus::RECEIVED || $inbound->voided_at) {
            return back()->with('error', ['Gagal', 'Hanya transaksi berstatus Diterima yang bisa dibatalkan.']);
        }

        $stock->voidInbound($inbound, $data['void_reason']);

        return back()->with('success', 'Transaksi Berhasil Dibatalkan');
    }

    public function bulkApprove(Request $request, StockService $stock): RedirectResponse
    {
        $count = 0;

        foreach (Inbound::whereIn('id', (array) $request->input('ids'))->get() as $inbound) {
            if ($inbound->status !== TransactionStatus::PENDING) {
                continue;
            }
            $stock->approveInbound($inbound, 'Barang Masuk (Setujui Massal)');
            $count++;
        }

        return $count > 0
            ? back()->with('success', "{$count} Transaksi Berhasil Disetujui")
            : back()->with('warning', 'Tidak ada transaksi pending yang dipilih');
    }

    /**
     * Hapus massal. Transaksi yang sudah Diterima tidak ikut dihapus karena stoknya
     * sudah bertambah — batalkan (void) dulu agar stok kembali.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $records = Inbound::whereIn('id', (array) $request->input('ids'))->get();
        $deletable = $records->reject(fn ($r) => $r->status === TransactionStatus::RECEIVED);

        $deletable->each->delete();
        $skipped = $records->count() - $deletable->count();

        return $skipped > 0
            ? back()->with('warning', ["{$deletable->count()} transaksi dihapus", "{$skipped} transaksi berstatus Diterima dilewati. Batalkan dulu agar stok dikembalikan."])
            : back()->with('success', 'Berhasil dihapus');
    }

    private function form(Inbound $inbound): View
    {
        $lines = old('items', $inbound->items?->map(fn ($l) => [
            'item_id' => $l->item_id,
            'quantity' => $l->quantity,
        ])->all() ?? []);

        return view('pages.inventory.inbounds.form', [
            'title' => $inbound->exists ? 'Ubah Barang Masuk' : 'Tambah Barang Masuk',
            'inbound' => $inbound,
            'code' => $inbound->transaction_code ?? Inbound::generateTransactionCode('IN'),
            'lines' => array_values($lines ?: [['item_id' => '', 'quantity' => '']]),
            'catalog' => ItemCatalog::forForms(),
            'supplierOptions' => Supplier::orderBy('name')->pluck('name', 'id'),
            'usesSuppliers' => StoreSetting::usesSuppliers(),
        ]);
    }

    /**
     * Harga beli diambil dari data supplier barang (bukan dari browser),
     * sama seperti isian otomatis Harga Beli yang read-only di versi Filament.
     *
     * @return array{header: array<string, mixed>, lines: array<int, array<string, mixed>>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            // Modul FNB tidak memakai supplier: harga beli diambil dari HPP barang.
            'supplier_id' => [StoreSetting::usesSuppliers() ? 'required' : 'nullable', Rule::exists('suppliers', 'id')],
            'supplier_invoice_no' => ['nullable', 'string', 'max:50'],
            'delivery_note_no' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', Rule::exists('items', 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ], [
            'items.required' => 'Tambahkan minimal satu barang.',
        ], [
            'date' => 'Tanggal',
            'supplier_id' => 'Supplier',
            'supplier_invoice_no' => 'No. Invoice Supplier',
            'delivery_note_no' => 'No. Surat Jalan',
            'items.*.item_id' => 'Barang',
            'items.*.quantity' => 'Kuantitas',
        ]);

        if (! StoreSetting::usesSuppliers()) {
            $data['supplier_id'] = null;
        }

        $prices = $data['supplier_id'] ? ItemSupplier::where('supplier_id', $data['supplier_id'])->pluck('harga_beli', 'item_id') : collect();
        $fallback = Item::whereIn('id', collect($data['items'])->pluck('item_id'))->pluck('harga_beli', 'id');

        $lines = collect($data['items'])->map(fn ($line) => [
            'item_id' => (int) $line['item_id'],
            'quantity' => (int) $line['quantity'],
            'buy_price' => (float) ($prices[$line['item_id']] ?? $fallback[$line['item_id']] ?? 0),
        ])->values()->all();

        return [
            'header' => collect($data)->only(['date', 'supplier_id', 'supplier_invoice_no', 'delivery_note_no', 'notes'])->all(),
            'lines' => $lines,
        ];
    }

    private function redirectAfterSave(Request $request, string $message): RedirectResponse
    {
        return $request->boolean('create_another')
            ? redirect()->route('inbounds.create')->with('success', $message)
            : redirect()->route('inbounds.index')->with('success', $message);
    }
}
