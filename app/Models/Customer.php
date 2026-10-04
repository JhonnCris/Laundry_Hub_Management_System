<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'contact_number',
        'email',
        'address',
    ];

    /** The display `name` is always built from the parts when they are given. */
    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            if (filled($customer->first_name) || filled($customer->last_name)) {
                $customer->name = collect([$customer->first_name, $customer->middle_name, $customer->last_name])
                    ->map(fn ($part) => trim((string) $part))
                    ->filter()
                    ->implode(' ');
            }
        });
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LaundryTransaction::class);
    }
}
