<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transaksi bisa dikaitkan ke member (dipilih dari kolom "Nama pelanggan" di POS).
 * customer_name tetap diisi (nama member) agar nota & laporan lama tetap bekerja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbounds', function (Blueprint $table) {
            $table->foreignId('member_id')->nullable()->after('customer_name')->constrained('members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('outbounds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('member_id');
        });
    }
};
