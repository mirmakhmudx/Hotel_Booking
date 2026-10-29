<?php

declare(strict_types=1);

namespace App\Hotel\Persistence;

use App\Hotel\Domain\DateRange;
use App\Models\Reservation;
use App\Models\Room;

/** Where reservations are kept. BookingService depends on this interface, not on the database. */
interface ReservationStore
{
    public function hasOverlap(Room $room, DateRange $stay): bool;

    public function save(Reservation $reservation): void;

    public function find(string $code): ?Reservation;

    public function countConfirmed(Room $room, DateRange $stay): int;
}
