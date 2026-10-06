<?php

namespace Tests;

use App\Models\Staff;
use App\Models\StaffAttendance;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Set to true before actingAs() to test a staff member who has not clocked in. */
    public bool $offDuty = false;

    /**
     * Staff users are clocked in automatically, because staff may only add
     * customers and record transactions while on duty.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        if (($user->role ?? null) === 'staff' && ! $this->offDuty && ! Staff::query()->where('email', $user->email)->exists()) {
            $staff = Staff::query()->create(['name' => $user->name, 'email' => $user->email, 'password' => $user->password, 'role' => 'staff']);
            StaffAttendance::query()->create(['staff_id' => $staff->id, 'work_date' => today(), 'clock_in' => now()]);
        }

        return parent::actingAs($user, $guard);
    }
}
