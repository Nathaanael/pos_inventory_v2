<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ItemSupplier;
use App\Models\ReturnTransaction;
use App\Models\Supplier;
use App\Services\StockService;
use App\Support\Format;
use App\Support\ItemCatalog;
use App\Support\StatusBadge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Transaksi Retur (fitur "retur").
 *  - Retur ke supplier  : saat disetujui stok berkurang.
 *  - Retur dari customer: saat disetujui stok bertambah.
 */
class ReturnTransactionController extends Controller
{
    public const TYPES = [
        'supplier' => 'Retur ke Supplier',
        'customer' => 'Retur dari Customer',
    ];

    public const CONDITIONS = [
        'baik' => 'Baik',
        'rusak' => 'Rusak',
        'cacat' => 'Cacat',
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = $request->query('status');

        $returns = ReturnTransaction::query()
            ->with('supplier')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('return_code', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%"))))
            ->when(in_array($status, ['pending', 'selesai', 'ditolak'], true), fn ($q) => $q->where('status', $status))
            ->latest('date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('pages.inventory.returns.index', [
            'title' => 'Transaksi Retur',
            'returns' => $returns,
        ]);
    }

    public function create(): View
    {
        return $this->form(new ReturnTransaction(['date' => now(), 'type' => 'supplier', 'status' => 'pending']));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
            $return = ReturnTransaction::create([
                ...$data['header'],
                'status' => 'pending',
                'created_by' => $request->user()->id,
                'total_amount' => collect($data['lines'])->sum(fn ($l) => $l['price'] * $l['quantity']),
            ]);

            foreach ($data['lines'] as $line) {
                $return->items()->create($line);
            }
        });

        return $request->boolean('create_another')
            ? redirect()->route('return-transactions.create')->with('success', 'Berhasil ditambah')
            : redirect()->route('return-transactions.index')->with('success', 'Berhasil ditambah');
    }

    public function edit(ReturnTransaction $returnTransaction): View|RedirectResponse
    {
        if (StatusBadge::value($returnTransaction->status) !== 'pending') {
            return redirect()->route('return-transactions.index')->with('error', ['Tidak bisa diubah', 'Hanya retur berstatus Pending yang bisa diubah.']);
        }

        return $this->form($returnTransaction->load('items'));
    }

    public function update(Request $request, ReturnTransaction $returnTransaction): RedirectResponse
    {
        abort_unless(StatusBadge::value($returnTransaction->status) === 'pending', 403, 'Hanya retur berstatus Pending yang bisa diubah.');

        $data = $this->validated($request);

        DB::transaction(function () use ($data, $returnTransaction) {
            $returnTransaction->update([
                ...$data['header'],
                'total_amount' => collect($data['lines'])->sum(fn ($l) => $l['price'] * $l['quantity']),
            ]);

            $returnTransaction->items()->delete();
            foreach ($data['lines'] as $line) {
                $returnTransaction->items()->create($line);
            }
        });

        return redirect()->route('return-transactions.index')->with('success', 'Berhasil diperbarui');
    }

    public function destroy(ReturnTransaction $returnTransaction): RedirectResponse
    {
        abort_unless(StatusBadge::value($returnTransaction->status) === 'pending', 403, 'Hanya retur berstatus Pending yang bisa dihapus.');

        $returnTransaction->delete();

        return redirect()->route('return-transactions.index')->with('success', 'Berhasil dihapus');
    }

    public function show(ReturnTransaction $returnTransaction): View
    {
        $returnTransaction->load(['supplier', 'items.item', 'createdBy']);
        [$status] = StatusBadge::for('return', $returnTransaction->status);

        return view('pages.inventory.partials.detail', [
            'fields' => array_filter([
                'Kode Retur' => $returnTransaction->return_code,
                'Tanggal' => Format::date($returnTransaction->date),
                'Tipe Retur' => self::TYPES[$returnTransaction->type] ?? $returnTransaction->type,
                'Supplier' => $returnTransaction->supplier?->name,
                'Nama Pelanggan' => $returnTransaction->type === 'customer' ? $returnTransaction->customer_name : null,
                'No. Referensi (Asal)' => $returnTransaction->reference_code,
                'Status' => $status,
                'Total Harga Retur' => Format::rupiah($returnTransaction->total_amount),
                'Dibuat Oleh' => $returnTransaction->createdBy?->name,
            ], fn ($v) => $v !== null),
            'notesLabel' => 'Alasan Retur',
            'notes' => $returnTransaction->reason,
            'columns' => ['Barang', 'Kuantitas', 'Harga', 'Kondisi', 'Subtotal'],
            'rows' => $returnTransaction->items->map(fn ($line) => [
                $line->item?->name ?? '-',
                Format::number($line->quantity),
                Format::rupiah($line->price),
                self::CONDITIONS[$line->condition] ?? $line->condition,
                Format::rupiah($line->subtotal),
            ])->all(),
            'footer' => [['label' => 'Total', 'value' => Format::rupiah($returnTransaction->total_amount)]],
        ]);
    }

    public function approve(ReturnTransaction $returnTransaction, StockService $stock): RedirectResponse
    {
        if (StatusBadge::value($returnTransaction->status) !== 'pending') {
            return back()->with('error', ['Gagal', 'Retur ini sudah diproses.']);
        }

        try {
            $stock->approveReturn($returnTransaction);
        } catch (ValidationException $e) {
            return back()->with('error', ['Gagal menyetujui', collect($e->errors())->flatten()->first()]);
        }

        return back()->with('success', 'Transaksi Berhasil Disetujui');
    }

    public function reject(ReturnTransaction $returnTransaction, StockService $stock): RedirectResponse
    {
        if (StatusBadge::value($returnTransaction->status) !== 'pending') {
            return back()->with('error', ['Gagal', 'Retur ini sudah diproses.']);
        }

        $stock->rejectReturn($returnTransaction);

        return back()->with('success', 'Transaksi Berhasil Ditolak');
    }

    public function bulkApprove(Request $request, StockService $stock): RedirectResponse
    {
        $records = ReturnTransaction::whereIn('id', (array) $request->input('ids'))->get()
            ->filter(fn ($r) => StatusBadge::value($r->status) === 'pending');

        try {
            DB::transaction(fn () => $records->each(fn ($r) => $stock->approveReturn($r, 'bulk_approve')));
        } catch (ValidationException $e) {
            return back()->with('error', ['Gagal menyetujui', collect($e->errors())->flatten()->first()]);
        }

        return back()->with('success', "{$records->count()} Transaksi Berhasil Disetujui");
    }

    public function bulkReject(Request $request, StockService $stock): RedirectResponse
    {
        $records = ReturnTransaction::whereIn('id', (array) $request->input('ids'))->get()
            ->filter(fn ($r) => StatusBadge::value($r->status) === 'pending');

        $records->each(fn ($r) => $stock->rejectReturn($r));

        return back()->with('success', "{$records->count()} Transaksi Berhasil Ditolak");
    }

    private function form(ReturnTransaction $return): View
    {
        $lines = old('items', $return->items?->map(fn ($l) => [
            'item_id' => $l->item_id,
            'quantity' => $l->quantity,
            'price' => (float) $l->price,
            'condition' => $l->condition,
        ])->all() ?? []);

        return view('pages.inventory.returns.form', [
            'title' => $return->exists ? 'Ubah Transaksi Retur' : 'Tambah Transaksi Retur',
            'return' => $return,
            'code' => $return->return_code ?? ReturnTransaction::generateTransactionCode('RET', 'return_code'),
            'lines' => array_values($lines ?: [['item_id' => '', 'quantity' => '', 'price' => '', 'condition' => 'rusak']]),
            'catalog' => ItemCatalog::forForms(),
            'supplierOptions' => Supplier::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /**
     * @return array{header: array<string, mixed>, lines: array<int, array<string, mixed>>}
     */
    private function validated(Request $request): array
    {
        $isSupplier = $request->input('type') === 'supplier';

        $data = $request->validate([
            'date' => ['required', 'date'],
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')],
            'customer_name' => [Rule::requiredIf(! $isSupplier), 'nullable', 'string', 'max:255'],
            'reference_code' => ['nullable', 'string', 'max:30'],
            'reason' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', Rule::exists('items', 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.condition' => ['required', Rule::in(array_keys(self::CONDITIONS))],
        ], [
            'items.required' => 'Tambahkan minimal satu barang.',
        ], [
            'date' => 'Tanggal',
            'type' => 'Tipe Retur',
            'supplier_id' => 'Supplier',
            'customer_name' => 'Nama Pelanggan',
            'reference_code' => 'No. Referensi',
            'reason' => 'Alasan Retur',
            'items.*.item_id' => 'Barang',
            'items.*.quantity' => 'Kuantitas Retur',
            'items.*.price' => 'Harga',
            'items.*.condition' => 'Kondisi',
        ]);

        // Retur ke supplier: barang harus dari supplier tsb dan stoknya cukup (aturan yang sama dengan form Filament).
        if ($isSupplier) {
            $pivots = ItemSupplier::with('item')->where('supplier_id', $data['supplier_id'])->get()->keyBy('item_id');
            $errors = [];

            foreach ($data['items'] as $i => $line) {
                $pivot = $pivots[$line['item_id']] ?? null;
                if (! $pivot) {
                    $errors["items.{$i}.item_id"] = 'Barang ini tidak disuplai oleh supplier yang dipilih.';
                } elseif ($line['quantity'] > $pivot->item->current_stock) {
                    $errors["items.{$i}.quantity"] = "Stok tidak cukup. Tersedia: {$pivot->item->current_stock}";
                } elseif ($line['quantity'] > $pivot->current_stock) {
                    $errors["items.{$i}.quantity"] = "Stok dari supplier ini tidak cukup. Tersedia: {$pivot->current_stock}";
                }
            }

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        }

        return [
            'header' => [
                'date' => $data['date'],
                'type' => $data['type'],
                'supplier_id' => $data['supplier_id'],
                'customer_name' => $isSupplier ? null : ($data['customer_name'] ?? null),
                'reference_code' => $data['reference_code'] ?? null,
                'reason' => $data['reason'],
            ],
            'lines' => collect($data['items'])->map(fn ($line) => [
                'item_id' => (int) $line['item_id'],
                'quantity' => (int) $line['quantity'],
                'price' => (float) $line['price'],
                'condition' => $line['condition'],
            ])->values()->all(),
        ];
    }
}
