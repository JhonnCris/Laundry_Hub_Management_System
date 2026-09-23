<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionClaim extends Model
{
    protected $fillable = [
        'laundry_transaction_id',
        'claimed_by_staff_id',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return ['claimed_at' => 'datetime'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LaundryTransaction::class, 'laundry_transaction_id');
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'claimed_by_staff_id');
    }
}
