<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis usaha & fitur yang aktif.
 *  - store_settings.business_type      : jenis usaha yang dipilih saat setup
 *  - store_settings.setup_completed_at : NULL = toko belum di-setup (wizard muncul untuk admin)
 *  - store_features                    : satu baris per fitur, aktif/tidak
 *
 * Data yang sudah ada sengaja dibiarkan "belum setup", jadi admin akan
 * melewati wizard sekali setelah migrasi ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->string('business_type', 30)->nullable()->after('store_name');
            $table->timestamp('setup_completed_at')->nullable()->after('printer_name');
        });

        Schema::create('store_features', function (Blueprint $table) {
            $table->id();
            $table->string('feature', 50)->unique();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_features');

        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn(['business_type', 'setup_completed_at']);
        });
    }
};
