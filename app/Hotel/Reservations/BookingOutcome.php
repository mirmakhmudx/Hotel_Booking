<?php

declare(strict_types=1);

namespace App\Hotel\Reservations;

enum BookingOutcome: string
{
    case Accepted = 'Accepted';
    case Unavailable = 'Unavailable';
    case InvalidDates = 'InvalidDates';
}
