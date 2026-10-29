<?php

declare(strict_types=1);

namespace App\Hotel\Support;

use Fiber;

/**
 * Lets other requests run while this one waits.
 * PHP has no threads, so two "simultaneous" requests are two Fibers that take turns
 * at exactly this point (see FiberScheduler).
 */
final class FiberPause implements Pause
{
    public function __construct(private readonly int $milliseconds)
    {
    }

    public function wait(): void
    {
        if (Fiber::getCurrent() === null) {
            usleep($this->milliseconds * 1000);

            return;
        }

        Fiber::suspend($this->milliseconds);
    }
}
