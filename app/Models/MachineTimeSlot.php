<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MachineTimeSlot extends Model
{
    protected $fillable = ['machine_id', 'slot_date', 'start_time', 'end_time', 'is_booked'];

    protected function casts(): array
    {
        return [
            'slot_date' => 'date',
            'is_booked' => 'boolean',
        ];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LaundryTransaction::class, 'machine_time_slot_id');
    }
}
