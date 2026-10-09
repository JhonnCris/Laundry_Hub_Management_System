<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Machine extends Model
{
    protected $fillable = ['name', 'type', 'size', 'status'];

    public function timeSlots(): HasMany
    {
        return $this->hasMany(MachineTimeSlot::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(TransactionMachine::class);
    }

    /** The self-service order currently running on this machine, if any. */
    public function activeOrder(): HasOneThrough
    {
        return $this->hasOneThrough(LaundryTransaction::class, TransactionMachine::class, 'machine_id', 'id', 'id', 'laundry_transaction_id')
            ->where('laundry_transactions.transaction_type', 'self_service')
            ->whereIn('laundry_transactions.status', ['pending', 'processing'])
            ->orderByDesc('laundry_transactions.id');
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
