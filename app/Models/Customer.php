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
        'phone_key',
    ];

    /** The display `name` is always built from the parts when they are given. */
    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            $customer->phone_key = static::phoneKey($customer->contact_number);

            if (filled($customer->first_name) || filled($customer->last_name)) {
                $customer->name = collect([$customer->first_name, $customer->middle_name, $customer->last_name])
                    ->map(fn ($part) => trim((string) $part))
                    ->filter()
                    ->implode(' ');
            }
        });
    }

    /** One normalised form of a phone number: 0917…, +63 917… and 63917… are the same number. */
    public static function phoneKey(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);
        if ($digits === '') {
            return null;
        }

        return str_starts_with($digits, '63') && strlen($digits) === 12 ? '0'.substr($digits, 2) : $digits;
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LaundryTransaction::class);
    }
}
