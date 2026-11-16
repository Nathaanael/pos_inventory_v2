<?php

namespace App\Models;

use App\Enums\OrderType;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Meja (modul FNB). Meja TERISI selama ada penjualan makan-di-tempat di meja ini
 * yang belum dikosongkan kasir (outbounds.table_released_at masih NULL).
 */
class DiningTable extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'capacity' => 'integer',
        ];
    }

    public function outbounds(): HasMany
    {
        return $this->hasMany(Outbound::class);
    }

    /** Penjualan yang sedang "menempati" meja ini. */
    public function activeOrders(): HasMany
    {
        return $this->outbounds()
            ->where('order_type', OrderType::DineIn->value)
            ->where('status', TransactionStatus::COMPLETED->value)
            ->whereNull('table_released_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** "T1 · Teras depan" atau "T1". */
    public function label(): string
    {
        return $this->name ? "{$this->number} · {$this->name}" : $this->number;
    }

    /** Kosongkan meja: semua pesanan aktif di meja ini ditandai selesai makan. */
    public function release(): int
    {
        return $this->activeOrders()->update(['table_released_at' => now()]);
    }
}
