<?php

declare(strict_types=1);

namespace App\Hotel\Rooms;

use Illuminate\Database\Eloquent\Collection;

/** Rooms' public contract: which rooms the hotel sells. */
interface RoomCatalog
{
    /** @return Collection<int, Room> */
    public function all(): Collection;
}
