<?php

namespace App\Exports;

use App\Models\Item;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class ItemStockExport extends BaseExport implements FromArray, WithTitle
{
    protected function getTitle(): string
    {
        return 'LAPORAN STOK BARANG';
    }

    public function title(): string
    {
        return 'Stok Barang';
    }

    protected function getHeaderRow(): array
    {
        return [
            'No',
            'Kode',
            'Nama Barang',
            'Kategori',
            'Brand',
            'Satuan',
            'Supplier',
            'Harga Beli',
            'Harga Jual Retail',
            'Harga Jual Grosir',
            'Min. Qty Grosir',
            'Stok Supplier',
            'Total Stok',
            'Min. Stok',
            'Lokasi Rak',
            'Status',
        ];
    }

    protected function getDataRows(): array
    {
        $items = Item::with(['category', 'brand', 'unit', 'itemSuppliers.supplier'])
            ->orderBy('name')
            ->get();

        $rows = [];
        $no = 1;
        foreach ($items as $item) {
            if ($item->itemSuppliers->isEmpty()) {
                // Item without any supplier — show with dash
                $rows[] = [
                    $no++,
                    $item->code,
                    $item->name,
                    $item->category?->name ?? '-',
                    $item->brand?->name ?? '-',
                    $item->unit?->name ?? '-',
                    '-',
                    $item->harga_beli,
                    $item->harga_jual_retail,
                    $item->harga_jual_grosir,
                    $item->min_grosir_qty,
                    '-',
                    $item->current_stock,
                    $item->min_stock,
                    $item->rack_location ?? '-',
                    $item->is_active ? 'Aktif' : 'Nonaktif',
                ];
            } else {
                // One row per item-supplier combination
                $isFirst = true;
                foreach ($item->itemSuppliers as $itemSupplier) {
                    $rows[] = [
                        $isFirst ? $no : '',
                        $isFirst ? $item->code : '',
                        $isFirst ? $item->name : '',
                        $isFirst ? ($item->category?->name ?? '-') : '',
                        $isFirst ? ($item->brand?->name ?? '-') : '',
                        $isFirst ? ($item->unit?->name ?? '-') : '',
                        $itemSupplier->supplier?->name ?? '-',
                        $itemSupplier->harga_beli,
                        $isFirst ? $item->harga_jual_retail : '',
                        $isFirst ? $item->harga_jual_grosir : '',
                        $isFirst ? $item->min_grosir_qty : '',
                        $itemSupplier->current_stock,
                        $isFirst ? $item->current_stock : '',
                        $isFirst ? $item->min_stock : '',
                        $isFirst ? ($item->rack_location ?? '-') : '',
                        $isFirst ? ($item->is_active ? 'Aktif' : 'Nonaktif') : '',
                    ];
                    $isFirst = false;
                }
                $no++;
            }
        }

        return $rows;
    }

    protected function getCurrencyColumns(): array
    {
        return ['H', 'I', 'J']; // Harga Beli, Harga Jual Retail, Harga Jual Grosir
    }

    protected function getColumnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 12,  // Kode
            'C' => 30,  // Nama Barang
            'D' => 15,  // Kategori
            'E' => 15,  // Brand
            'F' => 10,  // Satuan
            'G' => 22,  // Supplier
            'H' => 15,  // Harga Beli
            'I' => 16,  // Harga Jual Retail
            'J' => 16,  // Harga Jual Grosir
            'K' => 14,  // Min Qty Grosir
            'L' => 13,  // Stok Supplier
            'M' => 12,  // Total Stok
            'N' => 10,  // Min Stok
            'O' => 12,  // Lokasi Rak
            'P' => 10,  // Status
        ];
    }

    public function array(): array
    {
        return $this->buildSheet();
    }
}
