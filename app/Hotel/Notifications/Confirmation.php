<?php

declare(strict_types=1);

namespace App\Hotel\Notifications;

use Carbon\CarbonImmutable;

/**
 * Notifications' own message: the five fields from "Between contexts" in docs/contexts.md.
 * It no longer needs the Reservation class.
 */
final readonly class Confirmation
{
    public function __construct(
        public string $reservationId,
        public string $guestEmail,
        public string $roomNumber,
        public CarbonImmutable $checkIn,
        public CarbonImmutable $checkOut,
    ) {
    }
}
