<?php

namespace App\Models;

use App\Enums\OrderType;
use App\Enums\TransactionStatus;
use App\Traits\HasSequentialCode;
use Illuminate\Database\Eloquent\Model;

class Outbound extends Model
{
    use HasSequentialCode;

    public const PAYMENT_METHODS = [
        'tunai' => 'Tunai',
        'debit' => 'Kartu Debit',
        'qris' => 'QRIS / E-Wallet',
    ];

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'status' => TransactionStatus::class,
        'total_amount' => 'decimal:2',
        'order_type' => OrderType::class,
        'table_released_at' => 'datetime',
    ];

    public function diningTable()
    {
        return $this->belongsTo(DiningTable::class);
    }

    /** "Meja T1", "Take Away", atau null bila fitur FNB tidak dipakai. */
    public function orderLabel(): ?string
    {
        return match ($this->order_type) {
            OrderType::TakeAway => 'Take Away',
            OrderType::DineIn => $this->diningTable ? 'Meja ' . $this->diningTable->number : 'Makan di Tempat',
            default => null,
        };
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->transaction_code)) {
                $model->transaction_code = self::generateTransactionCode('OUT');
            }
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(OutboundItem::class);
    }
}