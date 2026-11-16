<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->enum('type', ['supplier', 'customer'])->default('supplier');
            $table->string('customer_name', 150)->nullable();
            $table->string('reference_code', 30)->nullable();
        });
    }

    public function down()
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->dropColumn(['type', 'customer_name', 'reference_code']);
        });
    }
};
