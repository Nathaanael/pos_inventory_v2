<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock opname kini juga dipakai modul FNB, yang stoknya desimal (mis. 1,25 kg bahan baku).
 * Detail (stock_opname_items) sudah decimal sejak migrasi FNB; total di header ikut disesuaikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->decimal('total_items_scanned', 15, 4)->default(0)->change();
            $table->decimal('total_difference', 15, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->unsignedInteger('total_items_scanned')->default(0)->change();
            $table->integer('total_difference')->default(0)->change();
        });
    }
};
