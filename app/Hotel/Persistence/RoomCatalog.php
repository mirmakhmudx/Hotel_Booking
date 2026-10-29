<?php

declare(strict_types=1);

namespace App\Hotel\Persistence;

use App\Models\Room;
use Illuminate\Database\Eloquent\Collection;

/** The rooms of the hotel. */
interface RoomCatalog
{
    /** @return Collection<int, Room> */
    public function all(): Collection;
}
