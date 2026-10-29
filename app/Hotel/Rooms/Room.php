<?php

declare(strict_types=1);

namespace App\Hotel\Rooms;

use Illuminate\Database\Eloquent\Model;

/**
 * Rooms' public type: a room the hotel sells.
 * Rooms knows nothing about reservations, so there is no reservations() relation here.
 */
class Room extends Model
{
    protected $fillable = ['number', 'type'];
}
