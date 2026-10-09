<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineRate extends Model
{
    public const WASH_MINUTES = 38;

    protected $fillable = ['size', 'kind', 'minutes', 'price', 'capacity_kg'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'capacity_kg' => 'decimal:1'];
    }
}
