<?php

namespace App\Services;

use App\Models\CashCount;
use App\Models\CashierShift;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Operasi shift kasir yang dipakai bersama panel kasir (tutup shift pribadi) dan back office
 * (shift bersama buatan admin).
 */
class ShiftService
{
    /**
     * Buka shift terjadwal: penanggung jawab mengisi modal awal hasil hitung laci, lalu shift berjalan.
     * Mengembalikan false bila shift sudah dibuka / tidak lagi terjadwal.
     */
    public function open(CashierShift $shift, float $openingCash, ?string $notes, User $by): bool
    {
        return DB::transaction(function () use ($shift, $openingCash, $notes, $by) {
            $locked = CashierShift::lockForUpdate()->findOrFail($shift->id);
            if ($locked->status !== CashierShift::STATUS_SCHEDULED) {
                return false;
            }

            $locked->update([
                'status' => CashierShift::STATUS_OPEN,
                'opened_at' => now(),
                'opened_by' => $by->id,
                'opening_cash' => $openingCash,
                'notes' => filled($notes) ? $notes : $locked->notes,
            ]);

            return true;
        });
    }

    /**
     * Tutup shift: simpan uang sistem vs uang fisik laci (Z-Report). Modul FNB juga mencatat Hitung Kas.
     *
     * @param  array{actual_cash: float, denominations: array<string, int>|null, notes: string|null}  $count
     */
    public function close(CashierShift $shift, array $count, User $by): void
    {
        DB::transaction(function () use ($shift, $count, $by) {
            $locked = CashierShift::lockForUpdate()->findOrFail($shift->id);
            if ($locked->status !== CashierShift::STATUS_OPEN) {
                return;
            }

            $summary = $locked->summary();
            $expected = (float) $summary['expected_cash'];
            $actual = (float) $count['actual_cash'];

            $locked->update([
                'closed_at' => now(),
                'closed_by' => $by->id,
                'expected_cash' => $expected,
                'actual_cash' => $actual,
                'cash_difference' => $actual - $expected,
                'status' => CashierShift::STATUS_CLOSED,
                'notes' => $count['notes'] ?? $locked->notes,
            ]);

            // Modul FNB: tutup shift otomatis tercatat di Hitung Kas (selisih di luar toleransi → perlu review admin).
            if (StoreSetting::usesCashCount()) {
                CashCount::record($locked, CashCount::TYPE_CLOSING, $actual, $count['denominations'], $by->id, $count['notes'], $summary);
            }
        });
    }
}
