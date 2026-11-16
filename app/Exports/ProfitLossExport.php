<?php

namespace App\Exports;

use App\Models\Outbound;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProfitLossExport extends BaseExport implements FromArray, WithTitle
{
    protected function getTitle(): string
    {
        return 'LAPORAN LABA RUGI';
    }

    public function title(): string
    {
        return 'Laba Rugi';
    }

    protected function getHeaderRow(): array
    {
        return [
            'No',
            'Tanggal',
            'Kode Transaksi',
            'Customer',
            'Total Penjualan',
            'Total HPP (COGS)',
            'Laba Kotor',
            'Margin (%)',
        ];
    }

    protected function getDataRows(): array
    {
        $outbounds = Outbound::with(['items'])
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->where('status', 'selesai')
            ->orderBy('date', 'desc')
            ->get();

        $rows = [];
        $no = 1;
        $grandTotalSales = 0;
        $grandTotalCogs = 0;
        $grandTotalProfit = 0;

        foreach ($outbounds as $outbound) {
            $totalSales = $outbound->total_amount;
            $totalCogs = $outbound->total_cogs;
            $profit = $totalSales - $totalCogs;
            $margin = $totalSales > 0 ? round(($profit / $totalSales) * 100, 1) : 0;

            $grandTotalSales += $totalSales;
            $grandTotalCogs += $totalCogs;
            $grandTotalProfit += $profit;

            $rows[] = [
                $no++,
                $outbound->date->format('d/m/Y'),
                $outbound->transaction_code,
                $outbound->customer_name ?? '-',
                $totalSales,
                $totalCogs,
                $profit,
                $margin . '%',
            ];
        }

        // Summary rows
        $grandMargin = $grandTotalSales > 0 ? round(($grandTotalProfit / $grandTotalSales) * 100, 1) : 0;
        $rows[] = [];
        $rows[] = [
            '', '', '', 'TOTAL',
            $grandTotalSales,
            $grandTotalCogs,
            $grandTotalProfit,
            $grandMargin . '%',
        ];

        return $rows;
    }

    protected function getCurrencyColumns(): array
    {
        return ['E', 'F', 'G']; // Total Penjualan, Total HPP, Laba Kotor
    }

    protected function getColumnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 14,  // Tanggal
            'C' => 18,  // Kode Transaksi
            'D' => 20,  // Customer
            'E' => 18,  // Total Penjualan
            'F' => 18,  // Total HPP
            'G' => 18,  // Laba Kotor
            'H' => 12,  // Margin
        ];
    }

    public function array(): array
    {
        return $this->buildSheet();
    }
}
