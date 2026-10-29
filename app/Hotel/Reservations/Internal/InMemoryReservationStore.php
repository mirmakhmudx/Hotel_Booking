<?php

declare(strict_types=1);

namespace App\Hotel\Reservations\Internal;

use App\Hotel\Reservations\DateRange;
use App\Hotel\Reservations\Reservation;
use App\Hotel\Reservations\ReservationStore;
use App\Hotel\Rooms\Room;

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
}
