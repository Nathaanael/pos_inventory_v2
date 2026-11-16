<?php

namespace App\Exports;

use App\Models\Outbound;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class OutboundReportExport extends BaseExport implements FromArray, WithTitle
{
    protected function getTitle(): string
    {
        return 'LAPORAN BARANG KELUAR (PENJUALAN)';
    }

    public function title(): string
    {
        return 'Barang Keluar';
    }

    protected function getHeaderRow(): array
    {
        return [
            'No',
            'Kode Transaksi',
            'Tanggal',
            'Customer',
            'Tujuan',
            'Tipe Harga',
            'Status',
            'Nama Barang',
            'Qty',
            'Harga Jual',
            'HPP',
            'Subtotal',
            'Profit',
            'Dibuat Oleh',
        ];
    }

    protected function getDataRows(): array
    {
        $outbounds = Outbound::with(['items.item', 'createdBy'])
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->orderBy('date', 'desc')
            ->get();

        $rows = [];
        $no = 1;
        foreach ($outbounds as $outbound) {
            $isFirst = true;
            foreach ($outbound->items as $detail) {
                $rows[] = [
                    $isFirst ? $no : '',
                    $isFirst ? $outbound->transaction_code : '',
                    $isFirst ? $outbound->date->format('d/m/Y') : '',
                    $isFirst ? ($outbound->customer_name ?? '-') : '',
                    $isFirst ? ($outbound->destination ?? '-') : '',
                    $isFirst ? ($outbound->price_type_applied ?? '-') : '',
                    $isFirst ? ($outbound->status?->value ?? '-') : '',
                    $detail->item?->name ?? '-',
                    $detail->quantity,
                    $detail->sell_price,
                    $detail->cogs_price,
                    $detail->subtotal,
                    $detail->profit,
                    $isFirst ? ($outbound->createdBy?->name ?? '-') : '',
                ];
                $isFirst = false;
            }
            if ($outbound->items->isEmpty()) {
                $rows[] = [
                    $no,
                    $outbound->transaction_code,
                    $outbound->date->format('d/m/Y'),
                    $outbound->customer_name ?? '-',
                    $outbound->destination ?? '-',
                    $outbound->price_type_applied ?? '-',
                    $outbound->status?->value ?? '-',
                    '-', 0, 0, 0, 0, 0,
                    $outbound->createdBy?->name ?? '-',
                ];
            }
            $no++;
        }

        // Summary row
        $totalQty = collect($rows)->sum(fn($r) => is_numeric($r[8]) ? $r[8] : 0);
        $totalSubtotal = collect($rows)->sum(fn($r) => is_numeric($r[11]) ? $r[11] : 0);
        $totalProfit = collect($rows)->sum(fn($r) => is_numeric($r[12]) ? $r[12] : 0);
        $rows[] = [];
        $rows[] = [
            '', '', '', '', '', '', 'TOTAL',
            '',
            $totalQty,
            '', '',
            $totalSubtotal,
            $totalProfit,
            '',
        ];

        return $rows;
    }

    protected function getCurrencyColumns(): array
    {
        return ['J', 'K', 'L', 'M']; // Harga Jual, HPP, Subtotal, Profit
    }

    protected function getColumnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 16,  // Kode Transaksi
            'C' => 12,  // Tanggal
            'D' => 18,  // Customer
            'E' => 16,  // Tujuan
            'F' => 12,  // Tipe Harga
            'G' => 12,  // Status
            'H' => 28,  // Nama Barang
            'I' => 8,   // Qty
            'J' => 15,  // Harga Jual
            'K' => 15,  // HPP
            'L' => 16,  // Subtotal
            'M' => 15,  // Profit
            'N' => 14,  // Dibuat Oleh
        ];
    }

    public function array(): array
    {
        return $this->buildSheet();
    }
}
