<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use SoftDeletes;

    public const TYPE_RETAIL = 'retail';
    public const TYPE_MENU = 'menu';
    public const TYPE_INGREDIENT = 'ingredient';
    public const TYPE_SEMI_FINISHED = 'semi_finished';

    public const TYPES = [
        self::TYPE_MENU => 'Menu Jadi (Dijual di Kasir)',
        self::TYPE_INGREDIENT => 'Bahan Baku (Dapur / Tidak Dijual)',
        self::TYPE_RETAIL => 'Barang Jual Langsung',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'current_stock' => 'float',
            'min_stock' => 'float',
            'track_stock' => 'boolean',
            'recipe_incomplete' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Legacy single-supplier relationship.
     * 
     * @deprecated Use itemSuppliers() for multi-supplier support.
     * Kept for backward compatibility with existing transaction forms
     * (Inbound, Outbound, Return) until those modules are updated.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Many-to-many relationship with suppliers via pivot table.
     * Use this for querying which suppliers can provide this item.
     */
    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'item_supplier')
            ->withPivot(['harga_beli', 'current_stock', 'supplier_sku'])
            ->withTimestamps();
    }

    /**
     * Direct access to pivot records as a hasMany relationship.
     * Used by Filament Repeater for CRUD operations on supplier data.
     */
    public function itemSuppliers()
    {
        return $this->hasMany(ItemSupplier::class);
    }

    public function prices()
    {
        return $this->hasMany(ItemPrice::class);
    }

    public function modifierGroups()
    {
        return $this->belongsToMany(ModifierGroup::class, 'item_modifier_group')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function recipe()
    {
        return $this->hasOne(Recipe::class, 'menu_item_id');
    }

    public function usedInRecipes()
    {
        return $this->hasMany(RecipeIngredient::class, 'ingredient_item_id');
    }

    public function hasRecipe(): bool
    {
        if ($this->relationLoaded('recipe')) {
            return $this->recipe !== null && $this->recipe->ingredients->isNotEmpty();
        }

        return $this->recipe()->whereHas('ingredients')->exists();
    }

    /** Menu berbasis resep yang bahannya masih kosong atau ditandai belum lengkap. */
    public function isRecipeIncomplete(): bool
    {
        if ($this->item_type !== self::TYPE_MENU || $this->track_stock) {
            return false;
        }

        return (bool) $this->recipe_incomplete || ! $this->hasRecipe();
    }

    /** Menu yang diproduksi on-demand dari resep (stok dipotong dari bahan baku, bukan stok menu). */
    public function usesRecipeStock(): bool
    {
        return $this->item_type === self::TYPE_MENU && ! $this->track_stock && $this->hasRecipe();
    }

    /** Hitung estimasi porsi tersedia berdasarkan stok bahan baku di resep. */
    public function availablePortions(): int
    {
        if ($this->isRecipeIncomplete()) {
            return 0;
        }

        if (! $this->usesRecipeStock()) {
            return (int) floor((float) $this->current_stock);
        }

        $recipe = $this->relationLoaded('recipe') ? $this->recipe : $this->recipe()->with('ingredients.ingredientItem')->first();
        if (! $recipe || $recipe->ingredients->isEmpty() || $recipe->yield_qty <= 0) {
            return 0;
        }

        $minPortions = null;
        foreach ($recipe->ingredients as $ing) {
            $neededPerPortion = $ing->quantityInBaseUnit() / $recipe->yield_qty;
            if ($neededPerPortion <= 0) {
                continue;
            }
            $stock = (float) ($ing->ingredientItem?->current_stock ?? 0);
            $portions = (int) floor($stock / $neededPerPortion);
            $minPortions = $minPortions === null ? $portions : min($minPortions, $portions);
        }

        return max(0, $minPortions ?? 0);
    }

    /** Hitung HPP (COGS) per 1 porsi dari total biaya bahan baku di resep. */
    public function calculateRecipeCogs(): float
    {
        if (! $this->hasRecipe()) {
            return (float) $this->harga_beli;
        }

        $recipe = $this->relationLoaded('recipe') ? $this->recipe : $this->recipe()->with('ingredients.ingredientItem')->first();
        if (! $recipe || $recipe->ingredients->isEmpty() || $recipe->yield_qty <= 0) {
            return (float) $this->harga_beli;
        }

        $totalCost = 0.0;
        foreach ($recipe->ingredients as $ing) {
            $needed = $ing->quantityInBaseUnit() / $recipe->yield_qty;
            $unitCost = (float) ($ing->ingredientItem?->harga_beli ?? 0);
            $totalCost += $needed * $unitCost;
        }

        return round($totalCost, 2);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function latestMovement()
    {
        return $this->hasOne(StockMovement::class)->latestOfMany();
    }

    /**
     * Sync aggregate/cache fields on items table from item_supplier data.
     * 
     * Updates:
     * - supplier_id: set to first supplier's ID (backward compat for transaction forms)
     * - harga_beli: set to first supplier's buy price (backward compat)
     * 
     * Note: current_stock is NOT recalculated here. It remains managed by
     * transaction events (InboundItem, OutboundItem, etc.) until those modules
     * are updated to also track per-supplier stock.
     */
    public function syncAggregateFields(): void
    {
        $primary = $this->itemSuppliers()->first();

        $updates = [];

        if ($primary) {
            $updates['supplier_id'] = $primary->supplier_id;
            $updates['harga_beli'] = $primary->harga_beli;
        } else {
            $updates['supplier_id'] = null;
            $updates['harga_beli'] = 0;
        }

        $this->update($updates);
    }

    /**
     * Calculate average COGS based on FIFO logic from item_supplier stocks.
     */
    public function calculateFifoCogs(float|int $quantity): float
    {
        if ($this->usesRecipeStock()) {
            return $this->calculateRecipeCogs();
        }

        if ($quantity <= 0) {
            return 0;
        }

        $supplierStocks = $this->itemSuppliers()
            ->where('current_stock', '>', 0)
            ->orderBy('id', 'asc')
            ->get();

        $remainingQty = $quantity;
        $totalCogs = 0;

        foreach ($supplierStocks as $supplierStock) {
            if ($remainingQty <= 0) break;

            $deduct = min($remainingQty, $supplierStock->current_stock);
            $totalCogs += ($deduct * $supplierStock->harga_beli);
            $remainingQty -= $deduct;
        }

        // If not enough stock in pivot, fallback to item's default harga_beli for the rest
        if ($remainingQty > 0) {
            $totalCogs += ($remainingQty * $this->harga_beli);
        }

        return $totalCogs / $quantity;
    }

    // Catatan: perubahan stok (termasuk potong stok FIFO per supplier)
    // ada di App\Services\StockService, bukan di model ini.
}