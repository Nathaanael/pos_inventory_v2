<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu shift bisa berisi banyak kasir (satu laci kas bersama).
 *  - cashier_shifts.name       : nama shift, mis. "Shift Pagi" (opsional).
 *  - cashier_shifts.opened_by  : siapa yang membuka/membuat shift (admin atau kasir).
 *  - cashier_shifts.closed_by  : siapa yang menutup shift.
 *  - cashier_shifts.user_id    : kini opsional — terisi bila shift dibuka sendiri oleh kasir (shift pribadi),
 *                                kosong bila shift dibuat admin untuk beberapa kasir.
 *  - cashier_shift_members     : kasir yang tergabung di shift.
 * Shift lama otomatis berisi satu anggota: pemiliknya (user_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->after('id');
            $table->foreignId('opened_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
        });

        // MySQL menolak mengubah kolom yang masih dipakai foreign key: lepas FK, ubah jadi nullable, pasang lagi.
        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('cashier_shift_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_shift_id')->constrained('cashier_shifts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cashier_shift_id', 'user_id']);
        });

        // Shift yang sudah ada: pemiliknya menjadi satu-satunya anggota & pembuka shift.
        DB::table('cashier_shifts')->whereNotNull('user_id')->orderBy('id')->each(function ($shift) {
            DB::table('cashier_shift_members')->insert([
                'cashier_shift_id' => $shift->id,
                'user_id' => $shift->user_id,
                'added_by' => $shift->user_id,
                'created_at' => $shift->opened_at,
                'updated_at' => $shift->opened_at,
            ]);
            DB::table('cashier_shifts')->where('id', $shift->id)->update(['opened_by' => $shift->user_id]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_shift_members');

        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('opened_by');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn('name');
        });
    }
};
