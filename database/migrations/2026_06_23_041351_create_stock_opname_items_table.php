<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->integer('system_stock');
            $table->integer('physical_stock');
            $table->integer('difference')->default(0);
            $table->enum('status', ['cocok', 'kurang', 'lebih'])->default('cocok');
            $table->string('notes')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();
            
            $table->unique(['stock_opname_id', 'item_id'], 'idx_so_item_unique');
            $table->index('status', 'idx_so_items_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
    }
};
