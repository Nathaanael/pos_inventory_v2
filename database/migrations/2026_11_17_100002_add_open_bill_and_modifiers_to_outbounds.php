<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul FNB:
 *  - outbounds: payment_status (paid/partial/unpaid) & paid_at untuk mendukung Open Bill (Pay Later).
 *  - outbound_items: snapshot modifier, total tambahan harga modifier, catatan item, dan status dapur.
 *  - outbound_payments: histori pembayaran (mendukung split payment / pelunasan open bill).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbounds', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('paid')->after('payment_method');
            $table->timestamp('paid_at')->nullable()->after('payment_status');
        });

        Schema::table('outbound_items', function (Blueprint $table) {
            // Di F&B, menu yang sama boleh muncul berkali-kali dalam satu transaksi (beda modifier/catatan/add-on).
            $table->index('outbound_id', 'idx_outbound_items_outbound_id');
            $table->dropUnique('idx_outbound_item_unique');

            $table->json('modifiers_snapshot')->nullable()->after('profit');
            $table->decimal('modifiers_total', 15, 2)->default(0)->after('modifiers_snapshot');
            $table->string('notes', 255)->nullable()->after('modifiers_total');
            $table->string('kitchen_status', 20)->default('queued')->after('notes');
        });

        Schema::create('outbound_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbound_id')->constrained('outbounds')->cascadeOnDelete();
            $table->string('payment_method', 30);
            $table->decimal('amount', 15, 2);
            $table->timestamp('paid_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_payments');

        Schema::table('outbound_items', function (Blueprint $table) {
            $table->dropColumn(['modifiers_snapshot', 'modifiers_total', 'notes', 'kitchen_status']);
        });

        Schema::table('outbounds', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'paid_at']);
        });
    }
};
