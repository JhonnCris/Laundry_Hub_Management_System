<?php

namespace App\Policies;

use App\Models\LaundryTransaction;
use App\Models\User;

class LaundryTransactionPolicy
{
    /** Only admins may cancel an order (it removes it from sales and returns stock). */
    public function cancel(User $user, LaundryTransaction $transaction): bool
    {
        return $user->role === 'admin';
    }
}
