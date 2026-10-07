<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = ['name', 'base_price', 'rate_per_kg', 'is_active'];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'rate_per_kg' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LaundryTransaction::class);
    }
}
