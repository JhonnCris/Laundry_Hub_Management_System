<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryRestock extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'staff_id',
        'quantity_received',
        'quantity_invoiced',
        'supplier',
        'invoice_number',
        'invoice_date',
        'cost',
        'notes',
        'restocked_at',
        'stocked_at',
        'stocked_quantity',
        'voided_at',
        'void_reason',
    ];

    protected $appends = ['receipt_status', 'remaining_quantity'];

    protected function casts(): array
    {
        return [
            'restocked_at' => 'date',
            'stocked_at' => 'datetime',
            'voided_at' => 'datetime',
            'invoice_date' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    /** pending | partial | stocked | voided */
    public function getReceiptStatusAttribute(): string
    {
        return match (true) {
            $this->voided_at !== null => 'voided',
            $this->stocked_quantity >= $this->quantity_received => 'stocked',
            $this->stocked_quantity > 0 => 'partial',
            default => 'pending',
        };
    }

    public function getRemainingQuantityAttribute(): int
    {
        return $this->voided_at ? 0 : max(0, $this->quantity_received - $this->stocked_quantity);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
