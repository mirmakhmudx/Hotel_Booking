<?php

declare(strict_types=1);

namespace App\Hotel\Reservations;

use App\Hotel\Rooms\Room;
use App\Hotel\Support\Pause;

/** Where reservations are kept. BookingService depends on this interface, not on the database. */
interface ReservationStore
{
    public function count(): int;

    public function hasOverlap(Room $room, DateRange $stay): bool;

    public function save(Reservation $reservation): void;

    public function find(string $code): ?Reservation;

    public function countConfirmed(Room $room, DateRange $stay): int;

    /**
     * P09 · Check that no Confirmed booking shares a night AND save, as one safe step.
     * Returns true when the reservation was saved, false when a night was already taken.
     * The pause is the "slow step" between the check and the save (zero in normal runs).
     */
    public function placeIfFree(Reservation $reservation, ConcurrencyStrategy $strategy, Pause $pause): bool;
}
