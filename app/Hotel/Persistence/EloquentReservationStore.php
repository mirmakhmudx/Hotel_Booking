<?php

declare(strict_types=1);

namespace App\Hotel\Persistence;

use App\Hotel\Domain\DateRange;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Database\Eloquent\Collection;

/**
 * PostgreSQL implementation of the store.
 *
 * The overlap rule is NOT repeated in SQL: we load the room's reservations and ask each one
 * Reservation::blocks(), which asks DateRange::overlaps(). One rule, one place.
 * P08/P09 move this check into the database.
 */
final class EloquentReservationStore implements ReservationStore
{
    public function hasOverlap(Room $room, DateRange $stay): bool
    {
        return $this->reservationsOf($room)
            ->contains(fn (Reservation $row): bool => $row->blocks($room, $stay));
    }

    public function save(Reservation $reservation): void
    {
        $reservation->save();
    }

    public function find(string $code): ?Reservation
    {
        $id = Reservation::idFromCode($code);

        if ($id === null) {
            return null;
        }

        return Reservation::query()->with(['guest', 'room'])->find($id);
    }

    public function countConfirmed(Room $room, DateRange $stay): int
    {
        return $this->reservationsOf($room)
            ->filter(fn (Reservation $row): bool => $row->blocks($room, $stay))
            ->count();
    }

    /** @return Collection<int, Reservation> */
    private function reservationsOf(Room $room): Collection
    {
        return Reservation::query()->where('room_id', $room->getKey())->get();
    }
}
