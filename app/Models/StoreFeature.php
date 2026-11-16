<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris per fitur toko (lihat App\Enums\Feature). Baca/ubah lewat
 * StoreSetting::hasFeature() dan StoreSetting::saveFeatures().
 */
class StoreFeature extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }
}
