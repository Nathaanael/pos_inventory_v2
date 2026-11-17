<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockOpname;
use App\Support\Format;
use App\Support\ItemCatalog;
use App\Support\ModuleView;
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
    /**
     * UI per modul: resources/views/pages/inventory/stock-opnames/{modul}/{index,form}.blade.php.
     * "default" dipakai modul selain FNB; FNB memakai folder "fnb" bila sudah dibuat.
     */
    private const VIEWS = 'pages.inventory.stock-opnames';

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

        return ModuleView::make(self::VIEWS, 'index', [
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
        $stockOpname->load(['items.item.unit', 'createdBy']);
        [$status] = StatusBadge::for('opname', $stockOpname->status);

        return view('pages.inventory.partials.detail', [
            'fields' => [
                'Kode Sesi' => $stockOpname->code,
                'Nama Sesi' => $stockOpname->session_name,
                'Tanggal' => Format::date($stockOpname->date),
                'Status' => $status,
                'Total Scan' => Format::qty($stockOpname->total_items_scanned),
                'Cocok' => Format::number($stockOpname->total_match),
                'Total Selisih' => Format::signedQty($stockOpname->total_difference),
                'Dibuat Oleh' => $stockOpname->createdBy?->name,
            ],
            'notesLabel' => 'Catatan',
            'notes' => $stockOpname->notes,
            'columns' => ['Barang', 'Stok Sistem', 'Stok Fisik', 'Selisih', 'Status', 'Catatan'],
            'rows' => $stockOpname->items->map(fn ($line) => [
                $line->item?->name ?? '-',
                trim(Format::qty($line->system_stock).' '.$line->item?->unit?->name),
                trim(Format::qty($line->physical_stock).' '.$line->item?->unit?->name),
                Format::signedQty($line->difference),
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

        return ModuleView::make(self::VIEWS, 'form', [
            'title' => $opname->exists ? 'Lanjutkan Stock Opname' : 'Tambah Stock Opname',
            'opname' => $opname,
            'code' => $opname->code ?? StockOpname::generateTransactionCode('SO', 'code'),
            'lines' => array_values($lines ?: [['item_id' => '', 'system_stock' => '', 'physical_stock' => '', 'notes' => '']]),
            // Hanya barang dengan stok dilacak; stok desimal apa adanya (bahan baku FNB).
            'catalog' => ItemCatalog::forOpname(),
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
            // Menu FNB berbasis resep (track_stock = false) tidak punya stok fisik sendiri.
            'items.*.item_id' => ['required', Rule::exists('items', 'id')->where('track_stock', true)],
            // Desimal sampai 4 digit (mis. 1,25 kg); modul retail tetap bisa mengisi bilangan bulat.
            'items.*.physical_stock' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
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
            $system = round((float) ($stocks[$row['item_id']] ?? 0), 4);
            $physical = round((float) $row['physical_stock'], 4);
            $diff = round($physical - $system, 4); // dibulatkan agar 1,1 - 1,0 tidak jadi 0,10000000000000009

            return [
                'item_id' => (int) $row['item_id'],
                'system_stock' => $system,
                'physical_stock' => $physical,
                'difference' => $diff,
                'status' => $diff == 0 ? 'cocok' : ($diff > 0 ? 'lebih' : 'kurang'),
                'notes' => $row['notes'] ?? null,
            ];
        })->values();

        return [
            'header' => [
                'session_name' => $data['session_name'],
                'date' => $data['date'],
                'notes' => $data['notes'] ?? null,
                'status' => $isDraft ? 'draft' : 'selesai',
                'total_items_scanned' => round($lines->sum('physical_stock'), 4),
                'total_match' => $lines->filter(fn ($line) => $line['difference'] == 0)->count(),
                'total_difference' => round($lines->sum('difference'), 4),
            ],
            'lines' => $lines->all(),
        ];
    }
}
