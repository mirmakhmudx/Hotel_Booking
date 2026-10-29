<?php

declare(strict_types=1);

namespace App\Hotel\Reservations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guest extends Model
{
    protected $fillable = ['name', 'email'];

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
