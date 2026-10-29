<?php

declare(strict_types=1);

namespace App\Hotel\Support;

/** The "slow step" between checking and saving. Normal runs do nothing, the race demo makes it slow. */
interface Pause
{
    public function wait(): void;
}
