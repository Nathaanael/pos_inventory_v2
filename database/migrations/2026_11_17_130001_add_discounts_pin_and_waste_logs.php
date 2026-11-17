<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4 Modul F&B:
 *  - outbounds: diskon per transaksi (percent / fixed).
 *  - users: pin_code (hashed) untuk otorisasi supervisor.
 *  - waste_logs: pencatatan bahan baku / menu terbuang / rusak.
 *  - stock_movements.type: tambah 'waste'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbounds', function (Blueprint $table) {
            $table->string('discount_type', 20)->nullable()->after('total_amount');
            $table->decimal('discount_value', 15, 2)->default(0)->after('discount_type');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_value');
            $table->string('discount_reason', 255)->nullable()->after('discount_amount');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_code', 255)->nullable()->after('password');
        });

        Schema::create('waste_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->string('reason', 100);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('wasted_at');
            $table->timestamps();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE stock_movements MODIFY COLUMN type VARCHAR(30) NOT NULL");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pin_code');
        });

        Schema::table('outbounds', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'discount_amount', 'discount_reason']);
        });
    }
};
