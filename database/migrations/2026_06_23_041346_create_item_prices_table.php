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
        Schema::create('item_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->enum('price_type', ['beli', 'retail', 'grosir']);
            $table->decimal('old_price', 15, 2);
            $table->decimal('new_price', 15, 2);
            $table->foreignId('changed_by')->constrained('users');
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable(); // keeping standard timestamps for ease
            
            $table->index(['item_id', 'created_at'], 'idx_item_prices_item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_prices');
    }
};
