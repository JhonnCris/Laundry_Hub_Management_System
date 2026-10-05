<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /** The self-service order currently running on this machine, if any. */
    public function activeOrder(): HasOne
    {
        return $this->hasOne(LaundryTransaction::class)
            ->where('transaction_type', 'self_service')
            ->whereIn('status', ['pending', 'processing'])
            ->latestOfMany();
    }

    /** Keep status in step with the orders: running order => in_use, none left => available. Maintenance is left alone. */
    public function syncUsage(): void
    {
        $running = $this->activeOrder()->exists();

        if ($running && $this->status === 'available') {
            $this->update(['status' => 'in_use']);
        } elseif (! $running && $this->status === 'in_use') {
            $this->update(['status' => 'available']);
        }
    }
}
