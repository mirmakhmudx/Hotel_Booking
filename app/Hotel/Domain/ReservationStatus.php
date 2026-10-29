<?php

declare(strict_types=1);

namespace App\Hotel\Domain;

/** A fixed list of allowed statuses: nothing else can be stored. */
enum ReservationStatus: string
{
    case Confirmed = 'Confirmed';
    case Cancelled = 'Cancelled';
}
