<?php

declare(strict_types=1);

namespace App\Hotel\Persistence;

use App\Models\Room;
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
