<?php

namespace App\Http\Controllers;

use App\Models\Outbound;
use App\Enums\TransactionStatus;

class OutboundPrintController extends Controller
{
    public function print(Outbound $outbound)
    {
        // Only allow printing for completed outbounds
        if ($outbound->status !== TransactionStatus::COMPLETED) {
            abort(403, 'Nota hanya bisa dicetak untuk transaksi yang sudah selesai.');
        }

        // Eager load relationships
        $outbound->load(['items.item.unit', 'createdBy']);

        return view('prints.outbound-receipt', compact('outbound'));
    }
}
