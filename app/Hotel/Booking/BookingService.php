<?php

declare(strict_types=1);

namespace App\Hotel\Booking;

use App\Hotel\Domain\DateRange;
use App\Hotel\Notification\NotificationSender;
use App\Hotel\Persistence\ReservationStore;
use App\Hotel\Persistence\RoomCatalog;
use App\Hotel\Support\Pause;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Runs each request in the right order: the sequence and activity diagrams as code.
 * Depends only on interfaces; the service provider decides which classes to plug in.
 *
 * Version 1 on purpose has NO transaction and NO lock between the check and the save,
 * so the race demo can show a double booking. P09 fixes this in the database.
 */
final class BookingService
{
    public function __construct(
        private readonly RoomCatalog $rooms,
        private readonly ReservationStore $store,
        private readonly NotificationSender $notifications,
        private readonly Pause $pause,
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

        if ($this->store->hasOverlap($room, $stay)) {
            return BookingResult::unavailable();
        }

        $this->pause->wait(); // check ... gap ... act

        $reservation = Reservation::place($guest, $room, $stay);
        $this->store->save($reservation);

        return $this->confirm($reservation);
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
    private function confirm(Reservation $reservation): BookingResult
    {
        try {
            $this->notifications->sendConfirmation($reservation);
        } catch (Throwable $failure) {
            report($failure);

            return BookingResult::accepted($reservation->code, notificationFailed: true);
        }

        return BookingResult::accepted($reservation->code);
    }
}
