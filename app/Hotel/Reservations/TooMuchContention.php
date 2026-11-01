<?php

declare(strict_types=1);

namespace App\Hotel\Reservations;

use RuntimeException;

/** Optimistic strategy only: the room changed under us on every attempt. The room may still be free. */
final class TooMuchContention extends RuntimeException
{
}
