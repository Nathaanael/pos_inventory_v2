<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OutboundItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'modifiers_snapshot' => 'array',
        'modifiers_total' => 'float',
    ];

    public function outbound()
    {
        return $this->belongsTo(Outbound::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $unitTotal = (float) $model->sell_price + (float) ($model->modifiers_total ?? 0);
            $model->subtotal = $unitTotal * $model->quantity;
            $model->profit = ($unitTotal - (float) $model->cogs_price) * $model->quantity;

            // 1.6 Validasi Stok Negatif (kecuali menu berbasis resep yang stoknya dicek di bahan baku oleh StockService)
            $item = Item::find($model->item_id);
            if ($item && ! $item->usesRecipeStock() && $item->current_stock < $model->quantity) {
                throw new \Exception(
                    "Stok {$item->name} tidak cukup. " .
                    "Tersedia: {$item->current_stock}, " .
                    "Diminta: {$model->quantity}"
                );
            }
        });
    }
}