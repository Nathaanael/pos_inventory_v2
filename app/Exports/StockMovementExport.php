<?php

namespace App\Exports;

use App\Models\StockMovement;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class StockMovementExport extends BaseExport implements FromArray, WithTitle
{
    protected function getTitle(): string
    {
        return 'LAPORAN MUTASI STOK';
    }

    public function title(): string
    {
        return 'Mutasi Stok';
    }

    protected function getHeaderRow(): array
    {
        return [
            'No',
            'Tanggal',
            'Nama Barang',
            'Kode Barang',
            'Tipe Pergerakan',
            'Referensi',
            'Qty',
            'Stok Sebelum',
            'Stok Sesudah',
            'Harga',
            'Keterangan',
            'Oleh',
        ];
    }

    protected function getDataRows(): array
    {
        $movements = StockMovement::with(['item', 'createdBy'])
            ->whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
            ->orderBy('created_at', 'desc')
            ->get();

        $typeLabels = [
            'inbound' => 'Barang Masuk',
            'outbound' => 'Barang Keluar',
            'return_in' => 'Retur Masuk',
            'return_out' => 'Retur Keluar',
            'adjustment' => 'Penyesuaian',
            'initial' => 'Stok Awal',
            'void' => 'Void',
        ];

        $rows = [];
        $no = 1;
        foreach ($movements as $movement) {
            $rows[] = [
                $no++,
                $movement->created_at->format('d/m/Y H:i'),
                $movement->item?->name ?? '-',
                $movement->item?->code ?? '-',
                $typeLabels[$movement->type] ?? $movement->type,
                $movement->reference_type ? ucfirst($movement->reference_type) . ' #' . $movement->reference_id : '-',
                $movement->quantity,
                $movement->stock_before,
                $movement->stock_after,
                $movement->price,
                $movement->notes ?? '-',
                $movement->createdBy?->name ?? '-',
            ];
        }

        return $rows;
    }

    protected function getCurrencyColumns(): array
    {
        return ['J']; // Harga
    }

    protected function getColumnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 16,  // Tanggal
            'C' => 28,  // Nama Barang
            'D' => 12,  // Kode Barang
            'E' => 16,  // Tipe Pergerakan
            'F' => 18,  // Referensi
            'G' => 8,   // Qty
            'H' => 12,  // Stok Sebelum
            'I' => 12,  // Stok Sesudah
            'J' => 15,  // Harga
            'K' => 20,  // Keterangan
            'L' => 14,  // Oleh
        ];
    }

    public function array(): array
    {
        return $this->buildSheet();
    }
}
