<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockOpname;
use App\Support\Format;
use App\Support\ItemCatalog;
use App\Support\StatusBadge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Stock Opname (fitur "stock_opname"). Mencatat hasil hitung fisik vs stok sistem.
 * Seperti versi Filament, sesi bisa disimpan sebagai Draft lalu dilanjutkan, atau Diselesaikan.
 * (Menyelesaikan sesi tidak mengubah stok barang.)
 */
class StockOpnameController extends Controller
{
    public const ITEM_STATUS = [
        'cocok' => 'Cocok',
        'kurang' => 'Kurang',
        'lebih' => 'Lebih',
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $opnames = StockOpname::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('session_name', 'like', "%{$search}%")))
            ->latest('date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('pages.inventory.stock-opnames.index', [
            'title' => 'Stock Opname',
            'opnames' => $opnames,
        ]);
    }

    public function create(): View
    {
        return $this->form(new StockOpname(['date' => now()]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
            $opname = StockOpname::create([...$data['header'], 'created_by' => $request->user()->id]);

            foreach ($data['lines'] as $line) {
                $opname->items()->create($line);
            }
        });

        return redirect()->route('stock-opnames.index')->with('success', 'Berhasil ditambah');
    }

    public function edit(StockOpname $stockOpname): View|RedirectResponse
    {
        if (StatusBadge::value($stockOpname->status) === 'selesai') {
            return redirect()->route('stock-opnames.index')->with('error', ['Tidak bisa diubah', 'Sesi yang sudah selesai tidak bisa dilanjutkan.']);
        }

        return $this->form($stockOpname->load('items'));
    }

    public function update(Request $request, StockOpname $stockOpname): RedirectResponse
    {
        abort_if(StatusBadge::value($stockOpname->status) === 'selesai', 403, 'Sesi yang sudah selesai tidak bisa diubah.');

        $data = $this->validated($request);

        DB::transaction(function () use ($data, $stockOpname) {
            $stockOpname->update($data['header']);

            $stockOpname->items()->delete();
            foreach ($data['lines'] as $line) {
                $stockOpname->items()->create($line);
            }
        });

        return redirect()->route('stock-opnames.index')->with('success', 'Berhasil diperbarui');
    }

    public function show(StockOpname $stockOpname): View
    {
        $stockOpname->load(['items.item', 'createdBy']);
        [$status] = StatusBadge::for('opname', $stockOpname->status);

        return view('pages.inventory.partials.detail', [
            'fields' => [
                'Kode Sesi' => $stockOpname->code,
                'Nama Sesi' => $stockOpname->session_name,
                'Tanggal' => Format::date($stockOpname->date),
                'Status' => $status,
                'Total Scan' => Format::number($stockOpname->total_items_scanned),
                'Cocok' => Format::number($stockOpname->total_match),
                'Total Selisih' => Format::signed($stockOpname->total_difference),
                'Dibuat Oleh' => $stockOpname->createdBy?->name,
            ],
            'notesLabel' => 'Catatan',
            'notes' => $stockOpname->notes,
            'columns' => ['Barang', 'Stok Sistem', 'Stok Fisik', 'Selisih', 'Status', 'Catatan'],
            'rows' => $stockOpname->items->map(fn ($line) => [
                $line->item?->name ?? '-',
                Format::number($line->system_stock),
                Format::number($line->physical_stock),
                Format::signed($line->difference),
                self::ITEM_STATUS[$line->status] ?? $line->status,
                $line->notes ?: '-',
            ])->all(),
        ]);
    }

    private function form(StockOpname $opname): View
    {
        $lines = old('items', $opname->items?->map(fn ($l) => [
            'item_id' => $l->item_id,
            'system_stock' => $l->system_stock,
            'physical_stock' => $l->physical_stock,
            'notes' => $l->notes,
        ])->all() ?? []);

        return view('pages.inventory.stock-opnames.form', [
            'title' => $opname->exists ? 'Lanjutkan Stock Opname' : 'Tambah Stock Opname',
            'opname' => $opname,
            'code' => $opname->code ?? StockOpname::generateTransactionCode('SO', 'code'),
            'lines' => array_values($lines ?: [['item_id' => '', 'system_stock' => '', 'physical_stock' => '', 'notes' => '']]),
            'catalog' => ItemCatalog::forForms(),
        ]);
    }

    /**
     * Tombol "Selesaikan" (submit_mode=selesai) mewajibkan semua baris lengkap;
     * "Draft" (submit_mode=draft) membuang baris yang belum memilih barang.
     *
     * @return array{header: array<string, mixed>, lines: array<int, array<string, mixed>>}
     */
    private function validated(Request $request): array
    {
        $isDraft = $request->input('submit_mode') === 'draft';

        if ($isDraft) {
            $request->merge([
                'items' => array_values(array_filter((array) $request->input('items'), fn ($row) => filled($row['item_id'] ?? null))),
            ]);
        }

        $data = $request->validate([
            'session_name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => [$isDraft ? 'nullable' : 'required', 'array'],
            'items.*.item_id' => ['required', Rule::exists('items', 'id')],
            'items.*.physical_stock' => ['required', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ], [
            'items.required' => 'Tambahkan minimal satu barang.',
        ], [
            'session_name' => 'Nama Sesi',
            'date' => 'Tanggal',
            'items.*.item_id' => 'Barang',
            'items.*.physical_stock' => 'Stok Fisik',
        ]);

        // Stok sistem dibaca dari database saat disimpan (dulu diisi otomatis & read-only di form).
        $stocks = Item::whereIn('id', collect($data['items'] ?? [])->pluck('item_id'))->pluck('current_stock', 'id');

        $lines = collect($data['items'] ?? [])->map(function ($row) use ($stocks) {
            $system = (int) ($stocks[$row['item_id']] ?? 0);
            $physical = (int) $row['physical_stock'];
            $diff = $physical - $system;

            return [
                'item_id' => (int) $row['item_id'],
                'system_stock' => $system,
                'physical_stock' => $physical,
                'difference' => $diff,
                'status' => $diff === 0 ? 'cocok' : ($diff > 0 ? 'lebih' : 'kurang'),
                'notes' => $row['notes'] ?? null,
            ];
        })->values();

        return [
            'header' => [
                'session_name' => $data['session_name'],
                'date' => $data['date'],
                'notes' => $data['notes'] ?? null,
                'status' => $isDraft ? 'draft' : 'selesai',
                'total_items_scanned' => $lines->sum('physical_stock'),
                'total_match' => $lines->where('difference', 0)->count(),
                'total_difference' => $lines->sum('difference'),
            ],
            'lines' => $lines->all(),
        ];
    }
}
