<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add supplier_id to items table
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
        });

        // 2. Migrate existing relationships from item_supplier table
        if (Schema::hasTable('item_supplier')) {
            $itemSuppliers = DB::table('item_supplier')->get();
            
            foreach ($itemSuppliers as $itemSupplier) {
                // Set the first supplier found for the item
                DB::table('items')
                    ->where('id', $itemSupplier->item_id)
                    ->whereNull('supplier_id')
                    ->update(['supplier_id' => $itemSupplier->supplier_id]);
            }

            // 3. Drop the old pivot table
            Schema::dropIfExists('item_supplier');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Recreate item_supplier table
        Schema::create('item_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        // 2. Migrate data back
        $items = DB::table('items')->whereNotNull('supplier_id')->get();
        foreach ($items as $item) {
            DB::table('item_supplier')->insert([
                'item_id' => $item->id,
                'supplier_id' => $item->supplier_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Drop supplier_id from items
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });
    }
};
