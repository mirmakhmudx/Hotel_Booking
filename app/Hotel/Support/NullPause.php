<?php

declare(strict_types=1);

namespace App\Hotel\Support;

final class NullPause implements Pause
{
    public function wait(): void
    {
        // zero normally
    }
}
