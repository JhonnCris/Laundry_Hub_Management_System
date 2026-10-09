<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionMachine extends Model
{
    protected $fillable = ['laundry_transaction_id', 'machine_id', 'minutes', 'price'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LaundryTransaction::class, 'laundry_transaction_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
