<?php

declare(strict_types=1);

namespace App\Hotel\Rooms\Internal;

use App\Hotel\Rooms\Room;
use App\Hotel\Rooms\RoomCatalog;
use Illuminate\Database\Eloquent\Collection;

/** A fixed list of rooms. Used by fast tests. */
final class InMemoryRoomCatalog implements RoomCatalog
{
    /** @param list<Room> $rooms */
    public function __construct(private readonly array $rooms)
    {
    }

    public function all(): Collection
    {
        return new Collection($this->rooms);
    }
}
