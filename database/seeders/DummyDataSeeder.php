<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Merk (Brand)
        $brands = [];
        foreach (['Philips', 'Eterna', 'Broco'] as $name) {
            $brands[] = \App\Models\Brand::firstOrCreate(['name' => $name]);
        }

        // 2. Kategori (Category)
        $categories = [];
        foreach (['Listrik', 'Bahan Bangunan', 'Peralatan'] as $name) {
            $categories[] = \App\Models\Category::firstOrCreate(['name' => $name], ['slug' => \Illuminate\Support\Str::slug($name)]);
        }

        // 3. Satuan (Unit)
        $units = [];
        foreach (['Pcs', 'Roll', 'Dus'] as $name) {
            $units[] = \App\Models\Unit::firstOrCreate(['name' => $name]);
        }

        // 4. Supplier
        $suppliers = [];
        $supplierData = [
            ['code' => 'SUP-001', 'name' => 'PT Makmur Jaya', 'contact_person' => 'Budi', 'phone' => '08123456789'],
            ['code' => 'SUP-002', 'name' => 'CV Sumber Terang', 'contact_person' => 'Agus', 'phone' => '08987654321'],
            ['code' => 'SUP-003', 'name' => 'Toko Sentosa', 'contact_person' => 'Siti', 'phone' => '08111222333'],
        ];
        foreach ($supplierData as $data) {
            $suppliers[] = \App\Models\Supplier::firstOrCreate(['code' => $data['code']], $data);
        }

        // 5. Master Barang (Item)
        $items = [];
        $itemData = [
            [
                'code' => 'BRG-001', 'name' => 'Lampu LED Philips 10W', 
                'brand_id' => $brands[0]->id, 'category_id' => $categories[0]->id, 'unit_id' => $units[0]->id, 'supplier_id' => $suppliers[0]->id,
                'harga_beli' => 25000, 'harga_jual_retail' => 30000, 'harga_jual_grosir' => 28000, 'min_grosir_qty' => 10, 'current_stock' => 0, 'min_stock' => 10
            ],
            [
                'code' => 'BRG-002', 'name' => 'Kabel Eterna NYM 2x1.5', 
                'brand_id' => $brands[1]->id, 'category_id' => $categories[0]->id, 'unit_id' => $units[1]->id, 'supplier_id' => $suppliers[1]->id,
                'harga_beli' => 350000, 'harga_jual_retail' => 400000, 'harga_jual_grosir' => 380000, 'min_grosir_qty' => 5, 'current_stock' => 0, 'min_stock' => 5
            ],
            [
                'code' => 'BRG-003', 'name' => 'Stop Kontak Broco', 
                'brand_id' => $brands[2]->id, 'category_id' => $categories[0]->id, 'unit_id' => $units[0]->id, 'supplier_id' => $suppliers[2]->id,
                'harga_beli' => 12000, 'harga_jual_retail' => 15000, 'harga_jual_grosir' => 14000, 'min_grosir_qty' => 20, 'current_stock' => 0, 'min_stock' => 15
            ],
        ];
        foreach ($itemData as $data) {
            $items[] = \App\Models\Item::firstOrCreate(['code' => $data['code']], $data);
        }

        // 6. Barang Masuk (Inbound) - Split into two suppliers (25 each)
        $inboundsData = [
            ['code' => 'IN-' . date('Y') . '-001', 'supplier_id' => $suppliers[0]->id, 'qty' => 25],
            ['code' => 'IN-' . date('Y') . '-002', 'supplier_id' => $suppliers[1]->id, 'qty' => 25],
        ];

        foreach ($inboundsData as $inbData) {
            $inbound = \App\Models\Inbound::firstOrCreate(
                ['transaction_code' => $inbData['code']],
                [
                    'date' => now()->toDateString(),
                    'supplier_id' => $inbData['supplier_id'],
                    'notes' => 'Stok awal dummy',
                    'status' => 'diterima',
                    'created_by' => 1,
                    'total_amount' => 0
                ]
            );

            if ($inbound->wasRecentlyCreated) {
                $totalAmount = 0;
                foreach ($items as $item) {
                    $qty = $inbData['qty'];
                    $buyPrice = $item->harga_beli;
                    $subtotal = $qty * $buyPrice;
                    $totalAmount += $subtotal;

                    \App\Models\InboundItem::create([
                        'inbound_id' => $inbound->id,
                        'item_id' => $item->id,
                        'quantity' => $qty,
                        'buy_price' => $buyPrice,
                        'subtotal' => $subtotal,
                    ]);

                    $stockBefore = $item->current_stock;
                    $item->current_stock += $qty;
                    $item->save();
                    
                    $itemSupplier = \App\Models\ItemSupplier::firstOrCreate(
                        ['item_id' => $item->id, 'supplier_id' => $inbData['supplier_id']],
                        ['harga_beli' => $buyPrice, 'current_stock' => 0]
                    );
                    $itemSupplier->current_stock += $qty;
                    $itemSupplier->save();

                    \App\Models\StockMovement::create([
                        'item_id' => $item->id,
                        'type' => 'inbound',
                        'reference_id' => $inbound->id,
                        'reference_type' => 'inbounds',
                        'quantity' => $qty,
                        'stock_before' => $stockBefore,
                        'stock_after' => $item->current_stock,
                        'price' => $buyPrice,
                        'notes' => 'Barang masuk dummy',
                        'created_by' => 1,
                    ]);
                }
                $inbound->update(['total_amount' => $totalAmount]);
            }
        }
    }
}
