<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table) {
            $table->id();
            $table->string('store_name', 100);
            $table->string('tagline', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->string('logo')->nullable();
            $table->string('receipt_footer', 255)->nullable();
            $table->string('printer_name', 100)->nullable();
            $table->timestamps();
        });

        // Satu baris pengaturan default; diubah lewat menu Pengaturan Toko.
        DB::table('store_settings')->insert([
            'store_name' => 'Toko Saya',
            'receipt_footer' => 'Terima Kasih',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
