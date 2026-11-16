<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbounds', function (Blueprint $table) {
            // tunai | debit | qris (null untuk transaksi lama / POS admin)
            $table->string('payment_method', 20)->nullable()->after('price_type_applied');
            // Uang yang diterima dari pelanggan (untuk tunai: hitung kembalian di struk)
            $table->decimal('paid_amount', 15, 2)->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('outbounds', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'paid_amount']);
        });
    }
};
