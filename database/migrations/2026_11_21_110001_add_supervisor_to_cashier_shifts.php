<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penanggung jawab shift: satu kasir yang membuka (mengisi modal awal) dan menutup shift.
 *  - cashier_shifts.supervisor_id : penanggung jawab.
 *  - status "scheduled"           : shift dibuat admin, belum dibuka penanggung jawab
 *                                   (opened_at kosong sampai dibuka).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->foreignId('supervisor_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->timestamp('opened_at')->nullable()->change();
        });

        // Shift lama: penanggung jawab = pemilik shift pribadi, atau anggota pertama shift bersama.
        DB::table('cashier_shifts')->whereNotNull('user_id')->update(['supervisor_id' => DB::raw('user_id')]);
        DB::table('cashier_shifts')->whereNull('user_id')->whereNull('supervisor_id')->orderBy('id')->each(function ($shift) {
            $first = DB::table('cashier_shift_members')->where('cashier_shift_id', $shift->id)->orderBy('id')->value('user_id');
            DB::table('cashier_shifts')->where('id', $shift->id)->update(['supervisor_id' => $first]);
        });
    }

    public function down(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_id');
        });
    }
};
