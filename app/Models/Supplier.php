<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $guarded = [];

    /**
     * Legacy one-to-many relationship.
     * 
     * @deprecated Use suppliedItems() or itemSuppliers() for multi-supplier support.
     * Kept for backward compatibility with existing transaction forms.
     */
    public function items()
    {
        return $this->hasMany(Item::class);
    }

    /**
     * Many-to-many relationship with items via pivot table.
     */
    public function suppliedItems()
    {
        return $this->belongsToMany(Item::class, 'item_supplier')
            ->withPivot(['harga_beli', 'current_stock', 'supplier_sku'])
            ->withTimestamps();
    }

    /**
     * Direct access to pivot records.
     */
    public function itemSuppliers()
    {
        return $this->hasMany(ItemSupplier::class);
    }

    public function inbounds()
    {
        return $this->hasMany(Inbound::class);
    }
}