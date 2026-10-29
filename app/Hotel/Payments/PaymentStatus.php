<?php

declare(strict_types=1);

namespace App\Hotel\Payments;

enum PaymentStatus: string
{
    case Paid = 'Paid';
    case Declined = 'Declined';
}
