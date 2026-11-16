<?php

namespace App\Exports;

use App\Models\StockOpname;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class StockOpnameExport extends BaseExport implements FromArray, WithTitle
{
    protected function getTitle(): string
    {
        return 'LAPORAN STOCK OPNAME';
    }

    public function title(): string
    {
        return 'Stock Opname';
    }

    protected function getHeaderRow(): array
    {
        return [
            'No',
            'Kode SO',
            'Nama Sesi',
            'Tanggal',
            'Status',
            'Nama Barang',
            'Kode Barang',
            'Stok Sistem',
            'Stok Fisik',
            'Selisih',
            'Status Item',
            'Keterangan',
            'Dibuat Oleh',
            'Disetujui Oleh',
        ];
    }

    protected function getDataRows(): array
    {
        $opnames = StockOpname::with(['items.item', 'createdBy', 'approvedBy'])
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->orderBy('date', 'desc')
            ->get();

        $rows = [];
        $no = 1;
        foreach ($opnames as $opname) {
            $isFirst = true;
            foreach ($opname->items as $detail) {
                $rows[] = [
                    $isFirst ? $no : '',
                    $isFirst ? $opname->code : '',
                    $isFirst ? $opname->session_name : '',
                    $isFirst ? $opname->date->format('d/m/Y') : '',
                    $isFirst ? ($opname->status?->value ?? '-') : '',
                    $detail->item?->name ?? '-',
                    $detail->item?->code ?? '-',
                    $detail->system_stock,
                    $detail->physical_stock,
                    $detail->difference,
                    $detail->status ?? '-',
                    $detail->notes ?? '-',
                    $isFirst ? ($opname->createdBy?->name ?? '-') : '',
                    $isFirst ? ($opname->approvedBy?->name ?? '-') : '',
                ];
                $isFirst = false;
            }
            if ($opname->items->isEmpty()) {
                $rows[] = [
                    $no,
                    $opname->code,
                    $opname->session_name,
                    $opname->date->format('d/m/Y'),
                    $opname->status?->value ?? '-',
                    '-', '-', 0, 0, 0, '-', '-',
                    $opname->createdBy?->name ?? '-',
                    $opname->approvedBy?->name ?? '-',
                ];
            }
            $no++;
        }

        // Summary
        $totalMatch = collect($rows)->filter(fn($r) => ($r[10] ?? '') === 'cocok')->count();
        $totalLess = collect($rows)->filter(fn($r) => ($r[10] ?? '') === 'kurang')->count();
        $totalMore = collect($rows)->filter(fn($r) => ($r[10] ?? '') === 'lebih')->count();

        $rows[] = [];
        $rows[] = [
            '', '', '', '', 'RINGKASAN',
            "Cocok: {$totalMatch}",
            "Kurang: {$totalLess}",
            "Lebih: {$totalMore}",
            '', '', '', '', '', '',
        ];

        return $rows;
    }

    protected function getCurrencyColumns(): array
    {
        return []; // No currency columns in stock opname
    }

    protected function getColumnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 14,  // Kode SO
            'C' => 20,  // Nama Sesi
            'D' => 12,  // Tanggal
            'E' => 12,  // Status
            'F' => 28,  // Nama Barang
            'G' => 12,  // Kode Barang
            'H' => 12,  // Stok Sistem
            'I' => 12,  // Stok Fisik
            'J' => 10,  // Selisih
            'K' => 12,  // Status Item
            'L' => 18,  // Keterangan
            'M' => 14,  // Dibuat Oleh
            'N' => 14,  // Disetujui Oleh
        ];
    }

    public function array(): array
    {
        return $this->buildSheet();
    }
}
