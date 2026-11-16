<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use SoftDeletes;

    protected $guarded = [];

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
    public function calculateFifoCogs(int $quantity): float
    {
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