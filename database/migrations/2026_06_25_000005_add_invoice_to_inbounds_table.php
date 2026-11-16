<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('inbounds', function (Blueprint $table) {
            $table->string('supplier_invoice_no', 50)->nullable();
            $table->string('delivery_note_no', 50)->nullable();
        });
    }

    public function down()
    {
        Schema::table('inbounds', function (Blueprint $table) {
            $table->dropColumn(['supplier_invoice_no', 'delivery_note_no']);
        });
    }
};
