<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'yield_qty' => 'float',
        ];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'menu_item_id');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }
}
