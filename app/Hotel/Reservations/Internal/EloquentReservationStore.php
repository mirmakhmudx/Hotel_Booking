<?php

declare(strict_types=1);

namespace App\Hotel\Reservations\Internal;

use App\Hotel\Reservations\ConcurrencyStrategy;
use App\Hotel\Reservations\DateRange;
use App\Hotel\Reservations\Reservation;
use App\Hotel\Reservations\ReservationStatus;
use App\Hotel\Reservations\ReservationStore;
use App\Hotel\Reservations\TooMuchContention;
use App\Hotel\Rooms\Room;
use App\Hotel\Support\Pause;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL implementation of the store.
 *
 * The overlap rule is NOT repeated in SQL: we load the room's reservations and ask each one
 * Reservation::blocks(), which asks DateRange::overlaps(). One rule, one place.
 *
 * P09: placeIfFree() is the only safe way to book. The exclusion constraint
 * (NoDoubleBookingConstraint) is the last line of defence for every strategy.
 */
final class EloquentReservationStore implements ReservationStore
{
    private const MAX_ATTEMPTS = 3;

    public function count(): int
    {
        return Reservation::query()->count();
    }

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

    public function placeIfFree(Reservation $reservation, ConcurrencyStrategy $strategy, Pause $pause): bool
    {
        return match ($strategy) {
            ConcurrencyStrategy::Unprotected => $this->unprotected($reservation, $pause),
            ConcurrencyStrategy::AtomicInsert => $this->atomicInsert($reservation, $pause),
            ConcurrencyStrategy::Optimistic => $this->optimistic($reservation, $pause),
            ConcurrencyStrategy::RowLock => $this->rowLock($reservation, $pause),
        };
    }

    /** Version 1, kept to show the bug: check ... gap ... save. */
    private function unprotected(Reservation $reservation, Pause $pause): bool
    {
        if ($this->hasOverlap($this->roomOf($reservation), $reservation->stay())) {
            return false;
        }

        $pause->wait();
        $reservation->save();

        return true;
    }

    /**
     * Repair 1 · atomic conditional insert: the check and the save are ONE statement, so there is no
     * gap in our code. Honest limit: under READ COMMITTED two such statements that run at the same
     * instant can both see "no clash" (neither sees the other's uncommitted row). What stops the
     * second one is the exclusion constraint: it waits for the first, then refuses (23P01).
     * That refusal is treated as "taken".
     * Found while testing: two truly simultaneous inserts can also DEADLOCK on the constraint (40P01).
     * PostgreSQL rolls one back; that is safe to repeat, and the repeat sees the committed winner.
     */
    private function atomicInsert(Reservation $reservation, Pause $pause): bool
    {
        $pause->wait(); // a slow request BEFORE the statement changes nothing: the statement itself decides

        $stay = $reservation->stay();

        $attempt = 0;

        do {
            $attempt++;
            $retry = false;

            try {
                $rows = DB::select(<<<'SQL'
                    INSERT INTO reservations (guest_id, room_id, check_in, check_out, status, created_at, updated_at)
                    SELECT ?::bigint, ?::bigint, ?::date, ?::date, ?::varchar, NOW(), NOW()
                    WHERE NOT EXISTS (
                        SELECT 1 FROM reservations
                        WHERE room_id = ?::bigint AND status = ?::varchar
                          AND check_in < ?::date AND check_out > ?::date
                    )
                    RETURNING id
                    SQL, [
                    $reservation->guest_id,
                    $reservation->room_id,
                    $stay->checkIn->toDateString(),
                    $stay->checkOut->toDateString(),
                    ReservationStatus::Confirmed->value,
                    $reservation->room_id,
                    ReservationStatus::Confirmed->value,
                    $stay->checkOut->toDateString(),
                    $stay->checkIn->toDateString(),
                ]);
            } catch (QueryException $failure) {
                if ($this->isExclusionViolation($failure)) {
                    return false;
                }

                if (! $this->isDeadlock($failure) || $attempt >= self::MAX_ATTEMPTS) {
                    throw $failure;
                }

                $retry = true; // we were the deadlock victim and were rolled back: ask again, the winner is committed by now
            }
        } while ($retry);

        if ($rows === []) {
            return false;
        }

        $reservation->setAttribute('id', (int) $rows[0]->id);
        $reservation->exists = true;
        $reservation->syncOriginal();

        return true;
    }

    /**
     * Repair 2 · optimistic version check: read the room's version, check, then bump the version in the
     * same transaction as the save, but only WHERE version is still the one we read. Zero rows changed
     * means someone booked this room in between: read again, check again (retry).
     */
    private function optimistic(Reservation $reservation, Pause $pause): bool
    {
        $room = $this->roomOf($reservation);
        $stay = $reservation->stay();

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $version = (int) DB::table('rooms')->where('id', $reservation->room_id)->value('version');

            if ($this->hasOverlap($room, $stay)) {
                return false;
            }

            $pause->wait();

            $saved = DB::transaction(function () use ($reservation, $version): bool {
                $bumped = DB::table('rooms')
                    ->where('id', $reservation->room_id)
                    ->where('version', $version)
                    ->update(['version' => $version + 1]);

                if ($bumped === 0) {
                    return false; // conflict: another booking changed this room first
                }

                $reservation->save();

                return true;
            });

            if ($saved) {
                return true;
            }
        }

        throw new TooMuchContention('Room ' . $reservation->room_id . ' changed on every one of ' . self::MAX_ATTEMPTS . ' attempts.');
    }

    /**
     * Repair 3 · short row-lock transaction: lock the room's row, check, save, commit.
     * A second request for the same room waits at the lock, then checks AFTER the first one committed,
     * so it sees the new booking. Keep the transaction short: nothing slow (email!) happens inside it.
     */
    private function rowLock(Reservation $reservation, Pause $pause): bool
    {
        $room = $this->roomOf($reservation);
        $stay = $reservation->stay();

        return DB::transaction(function () use ($reservation, $room, $stay, $pause): bool {
            DB::statement("SET LOCAL lock_timeout = '5s'"); // never wait forever
            DB::table('rooms')->where('id', $reservation->room_id)->lockForUpdate()->first();

            if ($this->hasOverlap($room, $stay)) {
                return false;
            }

            $pause->wait();
            $reservation->save();

            return true;
        });
    }

    private function roomOf(Reservation $reservation): Room
    {
        return (new Room())->forceFill(['id' => $reservation->room_id]);
    }

    private function isExclusionViolation(QueryException $failure): bool
    {
        return (string) $failure->getCode() === NoDoubleBookingConstraint::SQLSTATE
            || str_contains($failure->getMessage(), NoDoubleBookingConstraint::NAME);
    }

    /** SQLSTATE 40P01: PostgreSQL picked this statement as the deadlock victim and rolled it back. */
    private function isDeadlock(QueryException $failure): bool
    {
        return (string) $failure->getCode() === '40P01';
    }

    /** @return Collection<int, Reservation> */
    private function reservationsOf(Room $room): Collection
    {
        return Reservation::query()->where('room_id', $room->getKey())->get();
    }
}
