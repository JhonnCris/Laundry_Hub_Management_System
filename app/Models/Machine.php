<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Machine extends Model
{
    protected $fillable = ['name', 'type', 'status'];

    public function timeSlots(): HasMany
    {
        return $this->hasMany(MachineTimeSlot::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LaundryTransaction::class);
    }
}
