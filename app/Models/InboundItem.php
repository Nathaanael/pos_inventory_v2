<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboundItem extends Model
{
    protected $guarded = [];

    public function inbound()
    {
        return $this->belongsTo(Inbound::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->subtotal = $model->buy_price * $model->quantity;
        });

        static::created(function ($model) {
            if ($model->inbound->status->value !== \App\Enums\TransactionStatus::RECEIVED->value) {
                return;
            }

            // Barang masuk yang dibuat langsung berstatus "diterima": stok ditambah lewat StockService.
            app(\App\Services\StockService::class)->receiveInboundItem($model);
        });
    }
}