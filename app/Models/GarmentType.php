<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GarmentType extends Model
{
    protected $fillable = ['name', 'is_custom'];

    protected function casts(): array
    {
        return ['is_custom' => 'boolean'];
    }

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(LaundryTransaction::class, 'transaction_garments')
            ->withPivot('quantity')
            ->withTimestamps();
    }
}
