<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LaundryTransaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'total_amount' => 'decimal:2'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function basketTag(): BelongsTo
    {
        return $this->belongsTo(BasketTag::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(MachineTimeSlot::class, 'machine_time_slot_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function detergent(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'detergent_item_id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'handled_by');
    }

    public function garmentTypes(): BelongsToMany
    {
        return $this->belongsToMany(GarmentType::class, 'transaction_garments')->withPivot('quantity')->withTimestamps();
    }

    public function inventoryItems(): BelongsToMany
    {
        return $this->belongsToMany(InventoryItem::class, 'transaction_items')->withPivot('quantity', 'unit_price', 'line_total')->withTimestamps();
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(TransactionStatusLog::class);
    }

    public function claim(): HasOne
    {
        return $this->hasOne(TransactionClaim::class);
    }
}
