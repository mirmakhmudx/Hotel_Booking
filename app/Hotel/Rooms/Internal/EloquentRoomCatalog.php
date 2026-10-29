<?php

declare(strict_types=1);

namespace App\Hotel\Rooms\Internal;

use App\Hotel\Rooms\Room;
use App\Hotel\Rooms\RoomCatalog;
use Illuminate\Database\Eloquent\Collection;

/** The rooms from PostgreSQL. Only the wiring (HotelServiceProvider) names this class. */
final class EloquentRoomCatalog implements RoomCatalog
{
    public function all(): Collection
    {
        return Room::query()->orderBy('number')->get();
    }
}
