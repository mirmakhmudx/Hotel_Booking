<?php

declare(strict_types=1);

namespace App\Hotel\Persistence;

use App\Models\Room;
use Illuminate\Database\Eloquent\Collection;

final class EloquentRoomCatalog implements RoomCatalog
{
    public function all(): Collection
    {
        return Room::query()->orderBy('number')->get();
    }
}
