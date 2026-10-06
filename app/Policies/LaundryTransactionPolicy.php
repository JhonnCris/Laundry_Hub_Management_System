<?php

namespace App\Policies;

use App\Models\LaundryTransaction;
use App\Models\User;

class LaundryTransactionPolicy
{
    /** Only staff may cancel an order (it removes it from sales and returns stock); admins can only view cancelled orders. */
    public function cancel(User $user, LaundryTransaction $transaction): bool
    {
        return $user->role === 'staff';
    }
}
