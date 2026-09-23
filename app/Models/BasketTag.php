<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BasketTag extends Model
{
    protected $fillable = ['code', 'status'];

    public function transactions(): HasMany
    {
        return $this->hasMany(LaundryTransaction::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }
}
