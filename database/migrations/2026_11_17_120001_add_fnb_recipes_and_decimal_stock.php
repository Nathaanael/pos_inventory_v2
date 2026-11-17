<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 3 Modul F&B:
 *  - Dukungan stok desimal (decimal 15,4) untuk gramasi/volume bahan baku.
 *  - Pemisahan item_type (retail, menu, ingredient, semi_finished) & track_stock.
 *  - Konversi satuan (unit_conversions) dan Resep / Bill of Materials (recipes, recipe_ingredients).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('current_stock', 15, 4)->default(0)->change();
            $table->decimal('min_stock', 15, 4)->default(0)->change();
            $table->string('item_type', 20)->default('retail')->after('barcode');
            $table->boolean('track_stock')->default(true)->after('item_type');
        });

        Schema::table('item_supplier', function (Blueprint $table) {
            $table->decimal('current_stock', 15, 4)->default(0)->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('quantity', 15, 4)->change();
            $table->decimal('stock_before', 15, 4)->change();
            $table->decimal('stock_after', 15, 4)->change();
        });

        Schema::table('inbound_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 4)->change();
        });

        Schema::table('outbound_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 4)->change();
        });

        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->decimal('system_stock', 15, 4)->default(0)->change();
            $table->decimal('physical_stock', 15, 4)->default(0)->change();
            $table->decimal('difference', 15, 4)->default(0)->change();
        });

        Schema::table('return_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 4)->change();
        });

        Schema::create('unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('to_unit_id')->constrained('units')->cascadeOnDelete();
            $table->decimal('multiplier', 15, 6); // 1 from_unit = multiplier * to_unit
            $table->timestamps();

            $table->unique(['from_unit_id', 'to_unit_id']);
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->unique()->constrained('items')->cascadeOnDelete();
            $table->decimal('yield_qty', 15, 4)->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('ingredient_item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredients');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('unit_conversions');

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['item_type', 'track_stock']);
        });
    }
};
