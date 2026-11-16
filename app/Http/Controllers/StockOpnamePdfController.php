<?php

namespace App\Http\Controllers;

use App\Models\StockOpname;
use Barryvdh\DomPDF\Facade\Pdf;

class StockOpnamePdfController extends Controller
{
    public function download(StockOpname $stockOpname)
    {
        // Eager load necessary relationships
        $stockOpname->load(['items.item.unit', 'createdBy', 'approvedBy']);

        // Set paper to A4 and portrait
        $pdf = Pdf::loadView('prints.stock-opname-pdf', compact('stockOpname'))
                  ->setPaper('a4', 'portrait');

        $filename = 'Stock_Opname_' . $stockOpname->code . '.pdf';

        return $pdf->download($filename);
    }
}
