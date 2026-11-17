<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeIngredient extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function ingredientItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'ingredient_item_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Konversi jumlah takaran resep ke satuan dasar bahan baku (ingredientItem->unit_id).
     */
    public function quantityInBaseUnit(): float
    {
        $qty = (float) $this->quantity;
        $baseUnitId = $this->ingredientItem?->unit_id;

        if (! $this->unit_id || ! $baseUnitId || (int) $this->unit_id === (int) $baseUnitId) {
            return $qty;
        }

        return UnitConversion::convert($qty, (int) $this->unit_id, (int) $baseUnitId);
    }
}
