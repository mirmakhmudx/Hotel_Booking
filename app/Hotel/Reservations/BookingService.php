<?php

declare(strict_types=1);

namespace App\Hotel\Reservations;

use App\Hotel\Notifications\Confirmation;
use App\Hotel\Notifications\NotificationSender;
use App\Hotel\Rooms\Room;
use App\Hotel\Rooms\RoomCatalog;
use App\Hotel\Support\Pause;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Runs each request in the right order: the sequence and activity diagrams as code.
 * Owns the booking rule. Depends only on interfaces and on the public contracts of Rooms and
 * Notifications; the service provider decides which classes to plug in.
 *
 * P09: the check and the save are no longer two steps here. ReservationStore::placeIfFree() does both
 * as one safe step, using the chosen ConcurrencyStrategy; the database has the last word.
 */
final class BookingService
{
    public function __construct(
        private readonly RoomCatalog $rooms,
        private readonly ReservationStore $store,
        private readonly NotificationSender $notifications,
        private readonly Pause $pause,
        private readonly ConcurrencyStrategy $strategy = ConcurrencyStrategy::RowLock,
    ) {
    }

    /**
     * R1 · Search free rooms.
     *
     * @return Collection<int, Room>
     */
    public function findFreeRooms(DateRange $stay): Collection
    {
        return $this->rooms->all()
            ->reject(fn (Room $room): bool => $this->store->hasOverlap($room, $stay))
            ->values();
    }

    /** R2 + R3 + R6 · Book a room: check the dates, check for a clash, save, notify, answer. */
    public function reserve(Guest $guest, Room $room, DateRange $stay): BookingResult
    {
        if (! $stay->isValid()) {
            return BookingResult::invalidDates();
        }

        $reservation = Reservation::place($guest, $room, $stay);

        if (! $this->store->placeIfFree($reservation, $this->strategy, $this->pause)) {
            return BookingResult::unavailable(); // nothing was saved
        }

        // Notifications gets its own message: the five fields it needs, not the Reservation.
        return $this->confirm(new Confirmation(
            $reservation->code,
            (string) $guest->email,
            (string) $room->number,
            $stay->checkIn,
            $stay->checkOut,
        ));
    }

    /** R4 · Cancel: the reservation changes its own status and stays in the store as history. */
    public function cancel(string $reservationCode): bool
    {
        $reservation = $this->store->find($reservationCode);

        if ($reservation === null || ! $reservation->cancel()) {
            return false;
        }

        $this->store->save($reservation);

        return true;
    }

    /** R6: the booking is already saved, so a failed message must not undo or hide it. */
    private function confirm(Confirmation $confirmation): BookingResult
    {
        try {
            $this->notifications->sendConfirmation($confirmation);
        } catch (Throwable $failure) {
            report($failure);

            return BookingResult::accepted($confirmation->reservationId, notificationFailed: true);
        }

        return BookingResult::accepted($confirmation->reservationId);
    }
}
