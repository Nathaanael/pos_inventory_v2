<?php

namespace App\Exports;

use App\Models\Inbound;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class InboundReportExport extends BaseExport implements FromArray, WithTitle
{
    protected function getTitle(): string
    {
        return 'LAPORAN BARANG MASUK';
    }

    public function title(): string
    {
        return 'Barang Masuk';
    }

    protected function getHeaderRow(): array
    {
        return [
            'No',
            'Kode Transaksi',
            'Tanggal',
            'Supplier',
            'No. Invoice Supplier',
            'No. Surat Jalan',
            'Status',
            'Nama Barang',
            'Qty',
            'Harga Beli',
            'Subtotal',
            'Dibuat Oleh',
        ];
    }

    protected function getDataRows(): array
    {
        $inbounds = Inbound::with(['supplier', 'items.item', 'createdBy'])
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->orderBy('date', 'desc')
            ->get();

        $rows = [];
        $no = 1;
        foreach ($inbounds as $inbound) {
            $isFirst = true;
            foreach ($inbound->items as $detail) {
                $rows[] = [
                    $isFirst ? $no : '',
                    $isFirst ? $inbound->transaction_code : '',
                    $isFirst ? $inbound->date->format('d/m/Y') : '',
                    $isFirst ? ($inbound->supplier?->name ?? '-') : '',
                    $isFirst ? ($inbound->supplier_invoice_no ?? '-') : '',
                    $isFirst ? ($inbound->delivery_note_no ?? '-') : '',
                    $isFirst ? ($inbound->status?->value ?? '-') : '',
                    $detail->item?->name ?? '-',
                    $detail->quantity,
                    $detail->buy_price,
                    $detail->subtotal,
                    $isFirst ? ($inbound->createdBy?->name ?? '-') : '',
                ];
                $isFirst = false;
            }
            // If inbound has no items, still show the header row
            if ($inbound->items->isEmpty()) {
                $rows[] = [
                    $no,
                    $inbound->transaction_code,
                    $inbound->date->format('d/m/Y'),
                    $inbound->supplier?->name ?? '-',
                    $inbound->supplier_invoice_no ?? '-',
                    $inbound->delivery_note_no ?? '-',
                    $inbound->status?->value ?? '-',
                    '-',
                    0,
                    0,
                    0,
                    $inbound->createdBy?->name ?? '-',
                ];
            }
            $no++;
        }

        // Summary row
        $totalQty = collect($rows)->sum(fn($r) => is_numeric($r[8]) ? $r[8] : 0);
        $totalAmount = collect($rows)->sum(fn($r) => is_numeric($r[10]) ? $r[10] : 0);
        $rows[] = [];
        $rows[] = [
            '', '', '', '', '', '', 'TOTAL',
            '',
            $totalQty,
            '',
            $totalAmount,
            '',
        ];

        return $rows;
    }

    protected function getCurrencyColumns(): array
    {
        return ['J', 'K']; // Harga Beli, Subtotal
    }

    protected function getColumnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 16,  // Kode Transaksi
            'C' => 12,  // Tanggal
            'D' => 20,  // Supplier
            'E' => 18,  // No. Invoice
            'F' => 16,  // No. Surat Jalan
            'G' => 12,  // Status
            'H' => 28,  // Nama Barang
            'I' => 8,   // Qty
            'J' => 15,  // Harga Beli
            'K' => 16,  // Subtotal
            'L' => 14,  // Dibuat Oleh
        ];
    }

    public function array(): array
    {
        return $this->buildSheet();
    }
}
