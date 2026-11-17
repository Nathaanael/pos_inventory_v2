<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModifierGroup extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'min_select' => 'integer',
            'max_select' => 'integer',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(Modifier::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeModifiers(): HasMany
    {
        return $this->modifiers()->where('is_active', true);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_modifier_group');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /** Data ringkas untuk dikirim ke layar POS. */
    public function toPosGroup(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'min_select' => $this->min_select,
            'max_select' => $this->max_select,
            'is_required' => $this->is_required,
            'modifiers' => $this->activeModifiers->map(fn (Modifier $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'price_delta' => (float) $m->price_delta,
            ])->values()->all(),
        ];
    }
}
