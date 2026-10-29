<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Hotel\Notification\NotificationSender;
use App\Models\Reservation;
use RuntimeException;

/** A stand-in sender for tests: remembers what it would send, or fails on purpose. */
final class FakeNotificationSender implements NotificationSender
{
    /** @var list<string> */
    public array $sent = [];

    public function __construct(private readonly bool $fail = false)
    {
    }

    public function sendConfirmation(Reservation $reservation): void
    {
        if ($this->fail) {
            throw new RuntimeException('The mail server is down.');
        }

        $this->sent[] = $reservation->code;
    }
}
