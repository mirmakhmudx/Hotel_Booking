<?php

declare(strict_types=1);

namespace App\Api;

use App\Hotel\Reservations\ConcurrencyStrategy;
use App\Hotel\Reservations\DateRange;
use App\Hotel\Reservations\Reservation;
use App\Hotel\Reservations\ReservationStore;
use App\Hotel\Rooms\Room;
use App\Hotel\Support\Pause;

/**
 * Passes every call on to the real store and counts it: each call is one question to the database.
 * BookingService does not notice and does not change (the decorator idea from P04).
 */
final class CountingReservationStore implements ReservationStore
{
    public function __construct(
        private readonly ReservationStore $store,
        private readonly Stats $stats,
    ) {
    }

    public function count(): int
    {
        $this->stats->add('databaseQueries');

        return $this->store->count();
    }

    public function hasOverlap(Room $room, DateRange $stay): bool
    {
        $this->stats->add('databaseQueries');

        return $this->store->hasOverlap($room, $stay);
    }

    public function save(Reservation $reservation): void
    {
        $this->stats->add('databaseQueries');
        $this->store->save($reservation);
    }

    public function find(string $code): ?Reservation
    {
        $this->stats->add('databaseQueries');

        return $this->store->find($code);
    }

    public function countConfirmed(Room $room, DateRange $stay): int
    {
        $this->stats->add('databaseQueries');

        return $this->store->countConfirmed($room, $stay);
    }

    /** Counted as one call; the real store may send several statements (see docs/p09-protect-the-last-seat.md). */
    public function placeIfFree(Reservation $reservation, ConcurrencyStrategy $strategy, Pause $pause): bool
    {
        $this->stats->add('databaseQueries');

        return $this->store->placeIfFree($reservation, $strategy, $pause);
    }
}
