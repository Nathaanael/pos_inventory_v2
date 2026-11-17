<?php

namespace App\Support;

use App\Models\Item;

/**
 * Daftar barang (beserta harga & stok per supplier) untuk form transaksi di browser.
 * Dengan data ini, pilihan barang, harga beli, stok, dan sisa stok bisa dihitung langsung
 * tanpa bolak-balik ke server (dulu dilakukan Livewire lewat ->live()).
 */
class ItemCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forForms(): array
    {
        return Item::query()
            ->with('itemSuppliers')
            ->orderBy('name')
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'code' => $item->code,
                'stock' => (int) $item->current_stock,
                'harga_beli' => (float) $item->harga_beli,
                'harga_jual_retail' => (float) $item->harga_jual_retail,
                // supplier_id => { price, stock }
                'suppliers' => (object) $item->itemSuppliers->mapWithKeys(fn ($s) => [
                    $s->supplier_id => ['price' => (float) $s->harga_beli, 'stock' => (int) $s->current_stock],
                ])->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Barang untuk Stock Opname: hanya yang stoknya dilacak (menu FNB berbasis resep tidak punya
     * stok sendiri), dengan stok desimal apa adanya dan nama satuan.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forOpname(): array
    {
        return Item::query()
            ->where('track_stock', true)
            ->with('unit:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'current_stock', 'unit_id'])
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'code' => $item->code,
                'stock' => round((float) $item->current_stock, 4),
                'unit' => $item->unit?->name,
            ])
            ->values()
            ->all();
    }
}
