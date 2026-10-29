<?php

declare(strict_types=1);

namespace App\Hotel\Payments\Internal;

use App\Hotel\Payments\PaymentService;
use App\Hotel\Payments\PaymentStatus;

/** Simulated: no card and no network. A positive amount is Paid; anything else is Declined. */
final class SimulatedPaymentService implements PaymentService
{
    public function takeDeposit(string $reservationId, int $amountCents): PaymentStatus
    {
        return $amountCents > 0 ? PaymentStatus::Paid : PaymentStatus::Declined;
    }
}
