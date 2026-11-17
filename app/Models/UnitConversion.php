<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitConversion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'multiplier' => 'float',
        ];
    }

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'to_unit_id');
    }

    /**
     * Konversi $qty dari $fromUnitId ke $toUnitId.
     * Jika tidak ada aturan konversi langsung, cek kebalikannya (1 / multiplier), jika tidak ada kembalikan $qty apa adanya.
     */
    public static function convert(float $qty, int $fromUnitId, int $toUnitId): float
    {
        if ($fromUnitId === $toUnitId) {
            return $qty;
        }

        $direct = static::where('from_unit_id', $fromUnitId)->where('to_unit_id', $toUnitId)->value('multiplier');
        if ($direct !== null && (float) $direct > 0) {
            return $qty * (float) $direct;
        }

        $reverse = static::where('from_unit_id', $toUnitId)->where('to_unit_id', $fromUnitId)->value('multiplier');
        if ($reverse !== null && (float) $reverse > 0) {
            return $qty / (float) $reverse;
        }

        return $qty;
    }
}
