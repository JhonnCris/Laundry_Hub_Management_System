<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringBill extends Model
{
    protected $fillable = ['name', 'payee', 'category', 'usual_amount', 'is_active'];

    protected function casts(): array
    {
        return ['usual_amount' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function statements(): HasMany
    {
        return $this->hasMany(BillStatement::class);
    }
}
