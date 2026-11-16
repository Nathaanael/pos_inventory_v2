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
        Schema::create('inbounds', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code', 30)->unique();
            $table->date('date');
            $table->foreignId('supplier_id')->nullable()->constrained();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'diterima', 'dibatalkan'])->default('diterima');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            
            $table->index('date', 'idx_inbounds_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbounds');
    }
};
