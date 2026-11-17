<?php

namespace App\Models;

use App\Traits\HasSequentialCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hitung Kas (modul FNB): uang fisik di laci vs uang di sistem untuk satu shift kasir.
 * Selisih di luar toleransi (Pengaturan Toko) berstatus "perlu_review" sampai disetujui admin
 * dengan catatan penjelasan.
 */
class CashCount extends Model
{
    use HasSequentialCode;

    public const TYPE_SPOT = 'spot';

    public const TYPE_CLOSING = 'closing';

    public const TYPES = [
        self::TYPE_SPOT => 'Cek Dadakan',
        self::TYPE_CLOSING => 'Tutup Shift',
    ];

    public const STATUS_MATCH = 'cocok';

    public const STATUS_REVIEW = 'perlu_review';

    public const STATUS_APPROVED = 'disetujui';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'counted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'opening_cash' => 'float',
            'cash_sales' => 'float',
            'cash_in' => 'float',
            'cash_out' => 'float',
            'expected_cash' => 'float',
            'actual_cash' => 'float',
            'difference' => 'float',
            'tolerance' => 'float',
            'denominations' => 'array',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function needsReview(): bool
    {
        return $this->status === self::STATUS_REVIEW;
    }

    /**
     * Catat hitung kas untuk shift dengan snapshot angka sistem saat ini.
     *
     * @param  array<string, mixed>|null  $summary  hasil CashierShift::summary() bila sudah dihitung pemanggil
     * @param  array<string, int>|null  $denominations
     */
    public static function record(
        CashierShift $shift,
        string $type,
        float $actualCash,
        ?array $denominations,
        ?int $countedBy,
        ?string $notes = null,
        ?array $summary = null,
    ): self {
        $summary ??= $shift->summary();
        $expected = (float) $summary['expected_cash'];
        $difference = round($actualCash - $expected, 2);
        $tolerance = (float) StoreSetting::current()->cash_count_tolerance;

        return static::create([
            'code' => static::generateTransactionCode('HK', 'code'),
            'cashier_shift_id' => $shift->id,
            'type' => $type,
            'counted_by' => $countedBy,
            'counted_at' => now(),
            'opening_cash' => $summary['opening_cash'],
            'cash_sales' => $summary['cash_sales'],
            'cash_in' => $summary['cash_in'],
            'cash_out' => $summary['cash_out'],
            'expected_cash' => $expected,
            'actual_cash' => $actualCash,
            'difference' => $difference,
            'denominations' => $denominations,
            'tolerance' => $tolerance,
            'status' => abs($difference) <= $tolerance ? self::STATUS_MATCH : self::STATUS_REVIEW,
            'notes' => $notes,
        ]);
    }
}
