<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StockOpnameItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        // Desimal: stok FNB (bahan baku) bisa pecahan, mis. 1,25 kg.
        'system_stock' => 'float',
        'physical_stock' => 'float',
        'difference' => 'float',
    ];

    public function stockOpname()
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }


}