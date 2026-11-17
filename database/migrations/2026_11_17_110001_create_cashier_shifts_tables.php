<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shift Kasir & Cash Drawer (X-Report / Z-Report):
 *  - cashier_shifts  : buka/tutup shift, modal awal, estimasi kas, kas aktual (blind closing), selisih.
 *  - cash_movements  : kas masuk / kas keluar (petty cash) selama shift berjalan.
 *  - outbounds & outbound_payments: tautan ke cashier_shift_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->decimal('expected_cash', 15, 2)->nullable();
            $table->decimal('actual_cash', 15, 2)->nullable();
            $table->decimal('cash_difference', 15, 2)->nullable();
            $table->string('status', 20)->default('open'); // open | closed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_shift_id')->constrained('cashier_shifts')->cascadeOnDelete();
            $table->string('type', 10); // in | out
            $table->decimal('amount', 15, 2);
            $table->string('reason', 255);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('outbounds', function (Blueprint $table) {
            $table->foreignId('cashier_shift_id')->nullable()->after('created_by')
                ->constrained('cashier_shifts')->nullOnDelete();
        });

        Schema::table('outbound_payments', function (Blueprint $table) {
            $table->foreignId('cashier_shift_id')->nullable()->after('outbound_id')
                ->constrained('cashier_shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('outbound_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cashier_shift_id');
        });

        Schema::table('outbounds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cashier_shift_id');
        });

        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cashier_shifts');
    }
};
