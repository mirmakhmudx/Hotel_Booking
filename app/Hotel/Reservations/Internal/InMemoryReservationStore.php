<?php

declare(strict_types=1);

namespace App\Hotel\Reservations\Internal;

use App\Hotel\Reservations\ConcurrencyStrategy;
use App\Hotel\Reservations\DateRange;
use App\Hotel\Reservations\Reservation;
use App\Hotel\Reservations\ReservationStore;
use App\Hotel\Rooms\Room;
use App\Hotel\Support\Pause;

/** Keeps reservations in an array: no database needed. Used by fast tests. */
final class InMemoryReservationStore implements ReservationStore
{
    /** @var array<int, Reservation> keyed by reservation id */
    private array $rows = [];

    public function count(): int
    {
        return count($this->rows);
    }

    public function hasOverlap(Room $room, DateRange $stay): bool
    {
        foreach ($this->rows as $row) {
            if ($row->blocks($room, $stay)) {
                return true;
            }
        }

        return false;
    }

    public function save(Reservation $reservation): void
    {
        if ($reservation->getKey() === null) {
            $reservation->setAttribute('id', $this->count() + 1);
        }

        $this->rows[(int) $reservation->getKey()] = $reservation;
    }

    public function find(string $code): ?Reservation
    {
        $id = Reservation::idFromCode($code);

        return $id === null ? null : ($this->rows[$id] ?? null);
    }

    public function countConfirmed(Room $room, DateRange $stay): int
    {
        return count(array_filter(
            $this->rows,
            static fn (Reservation $row): bool => $row->blocks($room, $stay),
        ));
    }

    /**
     * SIMULATION, not a database: PHP runs one thing at a time, so the three protected strategies
     * have no gap to protect and just check and save. Only Unprotected pauses between the two steps,
     * which lets Fibers interleave and double-book. Real protection is tested in ConcurrentBookingTest.
     */
    public function placeIfFree(Reservation $reservation, ConcurrencyStrategy $strategy, Pause $pause): bool
    {
        $room = (new Room())->forceFill(['id' => $reservation->room_id]);

        if ($this->hasOverlap($room, $reservation->stay())) {
            return false;
        }

        if ($strategy === ConcurrencyStrategy::Unprotected) {
            $pause->wait();
        }

        $this->save($reservation);

        return true;
    }
}
