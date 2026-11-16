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
        Schema::create('inbound_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbound_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->decimal('buy_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->date('expiry_date')->nullable();
            $table->string('batch_number', 50)->nullable();
            $table->timestamps();
            
            $table->unique(['inbound_id', 'item_id'], 'idx_inbound_item_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbound_items');
    }
};
