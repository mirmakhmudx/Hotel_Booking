<?php

declare(strict_types=1);

namespace App\Hotel\Reservations;

use App\Hotel\Rooms\Room;

/** Where reservations are kept. BookingService depends on this interface, not on the database. */
interface ReservationStore
{
    public function count(): int;

    public function hasOverlap(Room $room, DateRange $stay): bool;

    public function save(Reservation $reservation): void;

    public function find(string $code): ?Reservation;

    public function countConfirmed(Room $room, DateRange $stay): int;
}
