<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryItem extends Model
{
    protected $fillable = [
        'inventory_category_id',
        'name',
        'unit',
        'unit_price',
        'quantity_on_hand',
        'low_stock_threshold',
        'status',
    ];

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
        return $this->belongsToMany(LaundryTransaction::class, 'transaction_items')
            ->withPivot('quantity', 'unit_price', 'line_total')
            ->withTimestamps();
    }

    /** Record a stock movement (+ in, - out) in the stock ledger. */
    public function logMovement(int $change, string $reason, ?int $staffId = null): void
    {
        $this->adjustments()->create(['staff_id' => $staffId, 'quantity_change' => $change, 'reason' => $reason]);
    }

    /** Remove expired/spoiled/damaged stock and keep a record of how many were removed. */
    public function archiveStock(string $reason, int $qty, ?int $staffId): void
    {
        DB::transaction(function () use ($reason, $qty, $staffId) {
            $item = static::query()->lockForUpdate()->findOrFail($this->id);
            if ($qty > $item->quantity_on_hand) {
                throw ValidationException::withMessages([
                    'quantity' => "Only {$item->quantity_on_hand} {$item->unit} of {$item->name} in stock; you cannot archive {$qty}.",
                ]);
            }

            $item->decrement('quantity_on_hand', $qty);
            $item->logMovement(-$qty, ucfirst($reason).InventoryAdjustment::ARCHIVE_SUFFIX, $staffId);
            // Nothing left to sell: hide the item until a receipt puts stock back.
            if ($item->fresh()->quantity_on_hand <= 0) {
                $item->update(['status' => 'archived_'.$reason]);
            }
        });
    }

    public function isLowStock(): bool
    {
        return $this->quantity_on_hand <= $this->low_stock_threshold;
    }
}
