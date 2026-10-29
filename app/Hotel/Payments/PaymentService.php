<?php

declare(strict_types=1);

namespace App\Hotel\Payments;

/**
 * Payments' public contract. Booking does not call it yet: the booking saga uses it in P17.
 * The amount is in cents.
 */
interface PaymentService
{
    public function takeDeposit(string $reservationId, int $amountCents): PaymentStatus;
}
