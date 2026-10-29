<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = ['number', 'type'];

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
