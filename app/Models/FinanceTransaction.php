<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceTransaction extends Model
{
    protected $fillable = [
        'type',
        'description',
        'category',
        'bill_statement_id',
        'reference_no',
        'amount',
        'staff_id',
        'laundry_transaction_id',
        'inventory_restock_id',
        'notes',
        'transaction_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function laundryTransaction(): BelongsTo
    {
        return $this->belongsTo(LaundryTransaction::class);
    }
}
