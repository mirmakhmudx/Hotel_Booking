<?php

declare(strict_types=1);

namespace App\Hotel\Booking;

use Stringable;

/** What Reserve returns: an outcome, plus the reservation id when something was saved. */
final readonly class BookingResult implements Stringable
{
    private function __construct(
        public BookingOutcome $outcome,
        public ?string $reservationCode = null,
        public bool $notificationFailed = false,
    ) {
    }

    public static function accepted(string $reservationCode, bool $notificationFailed = false): self
    {
        return new self(BookingOutcome::Accepted, $reservationCode, $notificationFailed);
    }

    public static function unavailable(): self
    {
        return new self(BookingOutcome::Unavailable);
    }

    public static function invalidDates(): self
    {
        return new self(BookingOutcome::InvalidDates);
    }

    public function isAccepted(): bool
    {
        return $this->outcome === BookingOutcome::Accepted;
    }

    public function __toString(): string
    {
        return $this->reservationCode === null
            ? $this->outcome->value
            : $this->outcome->value . ' ' . $this->reservationCode;
    }
}
