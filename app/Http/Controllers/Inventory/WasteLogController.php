<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\WasteLog;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pencatatan bahan baku / menu yang rusak, basi, atau terbuang (Waste / Spoilage).
 */
class WasteLogController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $logs = WasteLog::query()
            ->with(['item.unit', 'createdBy:id,name'])
            ->when($search !== '', fn ($q) => $q->whereHas('item', fn ($i) => $i->where('name', 'like', "%{$search}%")))
            ->latest('wasted_at')
            ->paginate(15)
            ->withQueryString();

        $items = Item::query()
            ->where('is_active', true)
            ->where('track_stock', true)
            ->with('unit:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'unit_id', 'current_stock']);

        return view('pages.inventory.waste-logs', [
            'title' => 'Waste / Bahan Terbuang',
            'logs' => $logs,
            'items' => $items,
            'reasons' => WasteLog::REASONS,
        ]);
    }

    public function store(Request $request, StockService $stock): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => ['required', Rule::exists('items', 'id')],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'reason' => ['required', Rule::in(array_keys(WasteLog::REASONS))],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], [
            'item_id' => 'Barang / Bahan',
            'quantity' => 'Jumlah',
            'reason' => 'Alasan',
        ]);

        $item = Item::findOrFail($data['item_id']);

        try {
            $stock->recordWaste($item, (float) $data['quantity'], $data['reason'], $data['notes'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'Waste berhasil dicatat dan stok telah disesuaikan.');
    }
}
