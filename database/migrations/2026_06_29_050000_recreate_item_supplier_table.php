<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Recreate item_supplier pivot table with extended fields for multi-supplier support.
     * 
     * Context: The original item_supplier table was dropped in migration 
     * 2026_06_26_092721 which moved to a direct supplier_id on items table.
     * This migration reverses that decision to support many-to-many item-supplier
     * relationships with different buy prices and per-supplier stock tracking.
     * 
     * IMPORTANT: items.supplier_id is NOT dropped — kept for backward compatibility
     * with existing transaction forms (Inbound, Outbound, Return) until those
     * modules are updated by the other team.
     */
    public function up(): void
    {
        // 1. Create item_supplier pivot table with extended fields
        Schema::create('item_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->decimal('harga_beli', 15, 2)->default(0);
            $table->integer('current_stock')->default(0);
            $table->string('supplier_sku', 50)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['item_id', 'supplier_id'], 'idx_item_supplier_unique');
        });

        // 2. Migrate existing data from items.supplier_id to item_supplier
        // This preserves all existing relationships without data loss
        if (Schema::hasColumn('items', 'supplier_id')) {
            $items = DB::table('items')->whereNotNull('supplier_id')->get();

            foreach ($items as $item) {
                DB::table('item_supplier')->insert([
                    'item_id' => $item->id,
                    'supplier_id' => $item->supplier_id,
                    'harga_beli' => $item->harga_beli ?? 0,
                    'current_stock' => $item->current_stock ?? 0,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migration: sync primary supplier data back to items table, then drop pivot.
     */
    public function down(): void
    {
        // Sync primary supplier data back to items table before dropping
        if (Schema::hasTable('item_supplier') && Schema::hasColumn('items', 'supplier_id')) {
            $primarySuppliers = DB::table('item_supplier')
                ->where('is_primary', true)
                ->get();

            foreach ($primarySuppliers as $ps) {
                DB::table('items')->where('id', $ps->item_id)->update([
                    'supplier_id' => $ps->supplier_id,
                    'harga_beli' => $ps->harga_beli,
                ]);
            }
        }

        Schema::dropIfExists('item_supplier');
    }
};
