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
        Schema::create('outbounds', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code', 30)->unique();
            $table->date('date');
            $table->enum('type', ['penjualan', 'penggunaan', 'retur', 'lainnya'])->default('penjualan');
            $table->string('customer_name', 150)->nullable();
            $table->string('destination', 200)->nullable();
            $table->enum('price_type_applied', ['retail', 'grosir'])->default('retail');
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'selesai', 'dibatalkan'])->default('selesai');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('total_cogs', 15, 2)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            
            $table->index('date', 'idx_outbounds_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbounds');
    }
};
