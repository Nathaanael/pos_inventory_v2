<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Category;
use App\Models\Unit;
use App\Models\Supplier;
use App\Models\Item;
use App\Models\Inbound;
use App\Models\InboundItem;
use App\Models\Outbound;
use App\Models\OutboundItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DashboardDummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Memulai generate dummy data untuk Dashboard...');

        // 1. Pastikan User ada
        $user = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            ['name' => 'Admin', 'password' => bcrypt('password')]
        );

        // 2. Setup Kategori
        $categories = ['Elektronik', 'Pakaian', 'Makanan', 'Minuman', 'Alat Tulis'];
        $categoryIds = [];
        foreach ($categories as $catName) {
            $cat = Category::firstOrCreate(['name' => $catName], ['slug' => Str::slug($catName)]);
            $categoryIds[$catName] = $cat->id;
        }

        // 3. Setup Unit
        $unitPcs = Unit::firstOrCreate(['name' => 'Pcs']);

        // 4. Setup Supplier
        $supplier = Supplier::firstOrCreate([
            'code' => 'SPL-001',
        ], [
            'name' => 'Supplier Utama',
            'email' => 'supplier@utama.com',
            'phone' => '08123456789'
        ]);

        // 5. Buat Barang (Items)
        $itemsData = [
            ['name' => 'Laptop Asus ROG', 'cat' => 'Elektronik', 'min' => 5, 'cogs' => 15000000, 'sell' => 17000000],
            ['name' => 'Mouse Wireless Logitech', 'cat' => 'Elektronik', 'min' => 10, 'cogs' => 150000, 'sell' => 250000],
            ['name' => 'Kaos Polos Hitam', 'cat' => 'Pakaian', 'min' => 20, 'cogs' => 30000, 'sell' => 75000],
            ['name' => 'Kemeja Flanel', 'cat' => 'Pakaian', 'min' => 10, 'cogs' => 80000, 'sell' => 150000],
            ['name' => 'Indomie Goreng (Karton)', 'cat' => 'Makanan', 'min' => 50, 'cogs' => 100000, 'sell' => 115000],
            ['name' => 'Biskuit Roma', 'cat' => 'Makanan', 'min' => 30, 'cogs' => 8000, 'sell' => 12000],
            ['name' => 'Air Mineral Botol', 'cat' => 'Minuman', 'min' => 100, 'cogs' => 2500, 'sell' => 4000],
            ['name' => 'Kopi Sachet', 'cat' => 'Minuman', 'min' => 50, 'cogs' => 10000, 'sell' => 15000],
            ['name' => 'Kertas A4 HVS', 'cat' => 'Alat Tulis', 'min' => 15, 'cogs' => 45000, 'sell' => 55000],
            ['name' => 'Pulpen Faster', 'cat' => 'Alat Tulis', 'min' => 100, 'cogs' => 2000, 'sell' => 3500],
        ];

        $items = [];
        foreach ($itemsData as $idx => $data) {
            $item = Item::firstOrCreate(
                ['name' => $data['name']],
                [
                    'code' => 'ITM-' . time() . '-' . str_pad($idx + 1, 4, '0', STR_PAD_LEFT),
                    'category_id' => $categoryIds[$data['cat']],
                    'unit_id' => $unitPcs->id,
                    'min_stock' => $data['min'],
                    'current_stock' => 0, // Akan ditambah dari Inbound
                    'harga_beli' => $data['cogs'],
                    'harga_jual_retail' => $data['sell'],
                    'harga_jual_grosir' => $data['sell'] * 0.9,
                ]
            );
            $items[] = [
                'model' => $item,
                'cogs' => $data['cogs'],
                'sell' => $data['sell']
            ];
        }

        // 6. Generate Inbounds (Pembelian ke Supplier untuk menambah stok awal)
        // Kita buat Inbound 31 hari yang lalu agar stok siap dijual
        $inboundDate = Carbon::now()->subDays(31);
        $inbound = Inbound::create([
            'transaction_code' => 'INB-' . time(),
            'date' => $inboundDate->format('Y-m-d'),
            'supplier_id' => $supplier->id,
            'status' => 'diterima',
            'notes' => 'Initial stock for dummy data',
            'created_by' => $user->id,
            'created_at' => $inboundDate,
            'updated_at' => $inboundDate,
            'total_amount' => 0, // diupdate nanti
        ]);

        $totalInbound = 0;
        foreach ($items as $itemData) {
            $qty = rand(50, 200);
            $buyPrice = $itemData['cogs'];
            $subtotal = $qty * $buyPrice;
            
            // Bypass eloquent events for created_at if we want past date, but OutboundItem booted uses saving
            InboundItem::create([
                'inbound_id' => $inbound->id,
                'item_id' => $itemData['model']->id,
                'quantity' => $qty,
                'buy_price' => $buyPrice,
                // subtotal is auto calculated by booted event
            ]);
            
            // To properly set dates on InboundItem and StockMovement which are auto-created
            $inboundItem = InboundItem::where('inbound_id', $inbound->id)->where('item_id', $itemData['model']->id)->first();
            $inboundItem->update(['created_at' => $inboundDate, 'updated_at' => $inboundDate]);
            
            $totalInbound += $subtotal;
        }
        $inbound->update(['total_amount' => $totalInbound]);

        // Tambahan Inbound "Hari Ini" untuk tes widget "Barang Masuk Hari Ini"
        $inboundToday = Inbound::create([
            'transaction_code' => 'INB-TODAY-' . time(),
            'date' => Carbon::now()->format('Y-m-d'),
            'supplier_id' => $supplier->id,
            'status' => 'diterima',
            'notes' => 'Restock Hari Ini',
            'created_by' => $user->id,
            'total_amount' => 0,
        ]);
        
        $itemToRestock = $items[array_rand($items)];
        InboundItem::create([
            'inbound_id' => $inboundToday->id,
            'item_id' => $itemToRestock['model']->id,
            'quantity' => 10,
            'buy_price' => $itemToRestock['cogs'],
        ]);
        $inboundToday->update(['total_amount' => 10 * $itemToRestock['cogs']]);


        // 7. Generate Outbounds (Penjualan)
        // Kita sebar penjualan dari 30 hari lalu sampai hari ini
        for ($day = 30; $day >= 0; $day--) {
            $date = Carbon::now()->subDays($day);
            // Random 1 sampai 3 transaksi per hari
            $transactionsCount = rand(1, 3);
            
            for ($t = 0; $t < $transactionsCount; $t++) {
                $outbound = Outbound::create([
                    'transaction_code' => 'OUT-' . $date->format('Ymd') . '-' . rand(1000, 9999),
                    'date' => $date->format('Y-m-d'),
                    'type' => 'penjualan',
                    'status' => 'selesai',
                    'notes' => 'Dummy Penjualan',
                    'created_by' => $user->id,
                    'created_at' => $date,
                    'updated_at' => $date,
                    'total_amount' => 0,
                ]);

                // 2 sampai 4 barang per transaksi
                $itemsCount = rand(2, 4);
                $selectedItems = collect($items)->random($itemsCount);
                $totalOutbound = 0;

                foreach ($selectedItems as $itemData) {
                    $qty = rand(1, 5);
                    $sellPrice = $itemData['sell'];
                    $cogsPrice = $itemData['cogs'];
                    
                    OutboundItem::create([
                        'outbound_id' => $outbound->id,
                        'item_id' => $itemData['model']->id,
                        'quantity' => $qty,
                        'cogs_price' => $cogsPrice,
                        'sell_price' => $sellPrice,
                    ]);
                    
                    // Update created_at
                    OutboundItem::where('outbound_id', $outbound->id)
                        ->where('item_id', $itemData['model']->id)
                        ->update(['created_at' => $date, 'updated_at' => $date]);
                    
                    $totalOutbound += ($qty * $sellPrice);
                }
                
                $outbound->update(['total_amount' => $totalOutbound]);
            }
        }
        
        // 8. Buat Alert Stok Menipis (Kurangi stok satu barang secara paksa sampai di bawah min_stock)
        $lowStockItem = $items[0]['model']; // Laptop Asus ROG (min: 5)
        $lowStockItem->current_stock = 2; // set under min_stock
        $lowStockItem->save();

        $this->command->info('Dummy data berhasil di-generate! Silakan cek halaman Dashboard.');
    }
}
