<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE inbounds MODIFY COLUMN status ENUM('pending', 'diterima', 'dibatalkan', 'voided') DEFAULT 'diterima'");
        DB::statement("ALTER TABLE outbounds MODIFY COLUMN status ENUM('pending', 'selesai', 'dibatalkan', 'voided') DEFAULT 'selesai'");
    }

    public function down(): void
    {
        // Down migration can fail if rows already have 'voided' status
        DB::statement("ALTER TABLE inbounds MODIFY COLUMN status ENUM('pending', 'diterima', 'dibatalkan') DEFAULT 'diterima'");
        DB::statement("ALTER TABLE outbounds MODIFY COLUMN status ENUM('pending', 'selesai', 'dibatalkan') DEFAULT 'selesai'");
    }
};
