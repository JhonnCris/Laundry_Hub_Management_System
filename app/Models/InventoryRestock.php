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
    ];

    protected function casts(): array
    {
        return [
            'restocked_at' => 'date',
            'invoice_date' => 'date',
            'cost' => 'decimal:2',
        ];
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
