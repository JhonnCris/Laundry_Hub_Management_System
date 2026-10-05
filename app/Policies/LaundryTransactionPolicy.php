<?php

namespace App\Policies;

use App\Models\LaundryTransaction;
use App\Models\User;

class LaundryTransactionPolicy
{
    /** Admins and staff may cancel an order (it removes it from sales and returns stock). */
    public function cancel(User $user, LaundryTransaction $transaction): bool
    {
        return in_array($user->role, ['admin', 'staff'], true);
    }
}
