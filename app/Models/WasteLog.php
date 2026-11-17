<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WasteLog extends Model
{
    public const REASONS = [
        'expired' => 'Kedaluwarsa / Basi',
        'spillage' => 'Tumpah / Rusak',
        'wrong_order' => 'Salah Masak / Order',
        'quality' => 'Tidak Layak Saji',
        'other' => 'Lainnya',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_cost' => 'float',
            'total_cost' => 'float',
            'wasted_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
