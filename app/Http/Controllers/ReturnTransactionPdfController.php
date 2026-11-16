<?php

namespace App\Http\Controllers;

use App\Models\ReturnTransaction;
use Barryvdh\DomPDF\Facade\Pdf;

class ReturnTransactionPdfController extends Controller
{
    public function download(ReturnTransaction $returnTransaction)
    {
        // Eager load necessary relationships
        $returnTransaction->load(['items.item.unit', 'createdBy', 'supplier']);

        // Set paper to A4 and portrait
        $pdf = Pdf::loadView('prints.return-transaction-pdf', compact('returnTransaction'))
                  ->setPaper('a4', 'portrait');

        $filename = 'Retur_Transaksi_' . $returnTransaction->return_code . '.pdf';

        return $pdf->download($filename);
    }
}
