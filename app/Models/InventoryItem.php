<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    public function restocks(): HasMany
    {
        return $this->hasMany(InventoryRestock::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class);
    }

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(LaundryTransaction::class, 'transaction_items')->withPivot('quantity', 'unit_price', 'line_total')->withTimestamps();
    }
}
