<?php

namespace App\Http\Controllers\Kasir;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Outbound;
use App\Models\OutboundItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Kitchen Display System (KDS) untuk dapur & bar (modul F&B).
 */
class KdsController extends Controller
{
    public const KITCHEN_STATUSES = ['queued', 'preparing', 'ready', 'served'];

    public function index(): View
    {
        return view('pages.kasir.kds', [
            'title' => 'Dapur (KDS)',
            'tickets' => $this->activeTickets(),
        ]);
    }

    public function data(): JsonResponse
    {
        return response()->json($this->activeTickets());
    }

    public function updateItemStatus(Request $request, OutboundItem $outboundItem): JsonResponse
    {
        $data = $request->validate([
            'kitchen_status' => ['required', Rule::in(self::KITCHEN_STATUSES)],
        ]);

        $outboundItem->update(['kitchen_status' => $data['kitchen_status']]);

        return response()->json([
            'ok' => true,
            'tickets' => $this->activeTickets(),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function activeTickets(): array
    {
        return Outbound::query()
            ->whereIn('status', [TransactionStatus::OPEN->value, TransactionStatus::COMPLETED->value])
            ->whereDate('created_at', today())
            ->whereHas('items', fn ($q) => $q->where('kitchen_status', '!=', 'served'))
            ->with([
                'diningTable:id,number,name',
                'items' => fn ($q) => $q->where('kitchen_status', '!=', 'served')->with('item:id,name'),
            ])
            ->orderBy('created_at')
            ->get()
            ->map(fn (Outbound $o) => [
                'id' => $o->id,
                'code' => $o->transaction_code,
                'order_label' => $o->orderLabel() ?? 'Pesanan',
                'customer' => $o->customer_name,
                'created_at' => $o->created_at->toIso8601String(),
                'time' => $o->created_at->format('H:i'),
                'items' => $o->items->map(fn (OutboundItem $l) => [
                    'id' => $l->id,
                    'name' => $l->item?->name ?? 'Menu',
                    'qty' => (float) $l->quantity,
                    'modifiers' => collect($l->modifiers_snapshot ?? [])->pluck('name')->all(),
                    'notes' => $l->notes,
                    'kitchen_status' => $l->kitchen_status ?? 'queued',
                ])->values()->all(),
            ])
            ->all();
    }
}
