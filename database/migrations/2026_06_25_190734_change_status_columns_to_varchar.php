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
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE stock_opnames MODIFY status VARCHAR(50) DEFAULT 'draft'");
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE returns MODIFY status VARCHAR(50) DEFAULT 'pending'");
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE outbounds MODIFY status VARCHAR(50) DEFAULT 'selesai'");
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE inbounds MODIFY status VARCHAR(50) DEFAULT 'diterima'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting back to enum is complex due to data loss risks, but we leave it as varchar
    }
};
