<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Master Pajak & Biaya Layanan (modul FNB), mis. PB1 10% atau Service Charge 5%.
 *  type       tax | service
 *  method     inclusive (sudah termasuk di harga) | exclusive (ditambahkan di atas harga)
 *  applies_to all | dine_in | take_away
 */
class Tax extends Model
{
    public const TYPES = [
        'tax' => 'Pajak',
        'service' => 'Biaya Layanan',
    ];

    public const METHODS = [
        'exclusive' => 'Exclusive (ditambahkan di atas harga)',
        'inclusive' => 'Inclusive (sudah termasuk di harga)',
    ];

    public const APPLIES_TO = [
        'all' => 'Semua pesanan',
        'dine_in' => 'Makan di Tempat',
        'take_away' => 'Take Away',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /** Data ringkas untuk kalkulator (server & browser). */
    public function toRule(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'rate' => (float) $this->rate,
            'method' => $this->method,
            'applies_to' => $this->applies_to,
        ];
    }
}
