<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Traits\HasSequentialCode;
use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
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
            if (empty($model->code)) {
                $model->code = self::generateTransactionCode('SO', 'code');
            }
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(StockOpnameItem::class);
    }
}