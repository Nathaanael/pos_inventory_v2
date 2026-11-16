<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE stock_movements MODIFY COLUMN type ENUM('inbound', 'outbound', 'return_in', 'return_out', 'adjustment', 'initial', 'void') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE stock_movements MODIFY COLUMN type ENUM('inbound', 'outbound', 'return_in', 'return_out', 'adjustment', 'initial') NOT NULL");
    }
};
