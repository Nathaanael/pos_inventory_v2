<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Traits\HasSequentialCode;
use Illuminate\Database\Eloquent\Model;

class Inbound extends Model
{
    use HasSequentialCode;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'status' => TransactionStatus::class,
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->transaction_code)) {
                $model->transaction_code = self::generateTransactionCode('IN');
            }
        });
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(InboundItem::class);
    }
}