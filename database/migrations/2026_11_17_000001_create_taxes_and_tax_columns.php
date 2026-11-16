<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pajak & Biaya Layanan (modul FNB):
 *  - taxes            : master PB1, service charge, dll.
 *  - items.tax_*      : override pajak per barang (opsional; kosong = ikut master).
 *  - outbounds.*      : snapshot pajak per transaksi.
 *      total_amount   tetap = penjualan barang (subtotal), agar laporan lama tidak berubah makna.
 *      grand_total    = yang dibayar pelanggan (subtotal + pajak/biaya exclusive).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 20)->default('tax');            // tax | service
            $table->decimal('rate', 5, 2);                          // persen, mis. 10.00
            $table->string('method', 20)->default('exclusive');    // inclusive | exclusive
            $table->string('applies_to', 20)->default('all');      // all | dine_in | take_away
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->nullable()->after('harga_jual_grosir');
            $table->string('tax_method', 20)->nullable()->after('tax_rate');
        });

        Schema::table('outbounds', function (Blueprint $table) {
            $table->decimal('service_amount', 15, 2)->default(0)->after('total_amount');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('service_amount');
            $table->decimal('grand_total', 15, 2)->nullable()->after('tax_amount');
            $table->json('tax_details')->nullable()->after('grand_total');
        });
    }

    public function down(): void
    {
        Schema::table('outbounds', function (Blueprint $table) {
            $table->dropColumn(['service_amount', 'tax_amount', 'grand_total', 'tax_details']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'tax_method']);
        });

        Schema::dropIfExists('taxes');
    }
};
