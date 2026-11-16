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
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('session_name', 100);
            $table->date('date');
            $table->enum('status', ['draft', 'in_progress', 'review', 'approved', 'rejected'])->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedInteger('total_items_scanned')->default(0);
            $table->unsignedInteger('total_match')->default(0);
            $table->unsignedInteger('total_difference')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            
            $table->index('date', 'idx_so_date');
            $table->index('status', 'idx_so_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opnames');
    }
};
