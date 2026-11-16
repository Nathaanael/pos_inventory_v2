<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul FNB: master meja + tipe pesanan (makan di tempat / take away).
 *
 * Meja dianggap TERISI selama ada penjualan "dine_in" di meja itu yang belum
 * dikosongkan kasir (outbounds.table_released_at masih NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();       // mis. "T1", "12"
            $table->string('name', 100)->nullable();      // mis. "Teras depan"
            $table->unsignedSmallInteger('capacity')->nullable(); // jumlah kursi
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('outbounds', function (Blueprint $table) {
            // dine_in | take_away (null bila fitur FNB tidak dipakai)
            $table->string('order_type', 20)->nullable()->after('payment_method');
            $table->foreignId('dining_table_id')->nullable()->after('order_type')
                ->constrained('dining_tables')->nullOnDelete();
            $table->timestamp('table_released_at')->nullable()->after('dining_table_id');
        });
    }

    public function down(): void
    {
        Schema::table('outbounds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dining_table_id');
            $table->dropColumn(['order_type', 'table_released_at']);
        });

        Schema::dropIfExists('dining_tables');
    }
};
