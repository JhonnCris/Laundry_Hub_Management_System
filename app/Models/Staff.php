<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    protected $table = 'staff';

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password'];

    public function attendance(): HasMany
    {
        return $this->hasMany(StaffAttendance::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LaundryTransaction::class, 'handled_by');
    }
}
