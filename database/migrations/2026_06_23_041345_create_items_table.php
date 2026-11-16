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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('barcode', 50)->unique()->nullable();
            $table->string('name', 200)->index();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained();
            $table->foreignId('unit_id')->constrained();
            $table->decimal('harga_beli', 15, 2)->default(0);
            $table->decimal('harga_jual_retail', 15, 2)->default(0);
            $table->decimal('harga_jual_grosir', 15, 2)->default(0);
            $table->unsignedInteger('min_grosir_qty')->default(0);
            $table->integer('current_stock')->default(0);
            $table->unsignedInteger('min_stock')->default(0);
            $table->string('rack_location', 50)->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['current_stock', 'min_stock'], 'idx_items_stock_alert');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
