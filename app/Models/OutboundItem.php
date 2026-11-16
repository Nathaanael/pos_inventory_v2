<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OutboundItem extends Model
{
    protected $guarded = [];

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
            $model->subtotal = $model->sell_price * $model->quantity;
            $model->profit = ($model->sell_price - $model->cogs_price) * $model->quantity;

            // 1.6 Validasi Stok Negatif
            $item = Item::find($model->item_id);
            if ($item && $item->current_stock < $model->quantity) {
                throw new \Exception(
                    "Stok {$item->name} tidak cukup. " .
                    "Tersedia: {$item->current_stock}, " .
                    "Diminta: {$model->quantity}"
                );
            }
        });
    }
}