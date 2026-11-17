<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hitung Kas (modul FNB): uang fisik di laci vs uang di sistem, per shift kasir.
 *  - type "spot"    : cek dadakan oleh admin saat shift berjalan.
 *  - type "closing" : otomatis dibuat saat kasir menutup shift.
 * Angka sistem disimpan sebagai snapshot saat dihitung, karena penjualan shift terus bertambah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_counts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('cashier_shift_id')->constrained('cashier_shifts')->cascadeOnDelete();
            $table->string('type', 20); // spot | closing
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at');

            // Snapshot angka sistem
            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->decimal('cash_sales', 15, 2)->default(0);
            $table->decimal('cash_in', 15, 2)->default(0);
            $table->decimal('cash_out', 15, 2)->default(0);
            $table->decimal('expected_cash', 15, 2)->default(0);

            $table->decimal('actual_cash', 15, 2)->default(0);
            $table->decimal('difference', 15, 2)->default(0);
            $table->json('denominations')->nullable(); // {"100000": 5, "50000": 2, ...}; null = input total langsung
            $table->decimal('tolerance', 15, 2)->default(0);

            $table->string('status', 20); // cocok | perlu_review | disetujui
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'counted_at']);
        });

        Schema::table('store_settings', function (Blueprint $table) {
            // Selisih hitung kas sampai nilai ini dianggap cocok (tidak perlu review).
            $table->decimal('cash_count_tolerance', 15, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn('cash_count_tolerance');
        });

        Schema::dropIfExists('cash_counts');
    }
};
