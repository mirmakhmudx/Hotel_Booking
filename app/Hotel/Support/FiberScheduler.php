<?php

declare(strict_types=1);

namespace App\Hotel\Support;

use Fiber;

/** A tiny scheduler: starts tasks at given times and resumes them when their pause is over. */
final class FiberScheduler
{
    /** @var list<array{at: int, fiber: Fiber}> */
    private array $queue = [];

    private int $now = 0;

    public function spawn(int $startAtMs, callable $task): void
    {
        $this->queue[] = ['at' => $startAtMs, 'fiber' => new Fiber($task)];
    }

    public function run(): void
    {
        while ($this->queue !== []) {
            usort($this->queue, static fn (array $a, array $b): int => $a['at'] <=> $b['at']);
            ['at' => $at, 'fiber' => $fiber] = array_shift($this->queue);

            if ($at > $this->now) {
                usleep(($at - $this->now) * 1000);
                $this->now = $at;
            }

            $waitMs = $fiber->isStarted() ? $fiber->resume() : $fiber->start();

            if ($fiber->isSuspended()) {
                $this->queue[] = ['at' => $this->now + (int) $waitMs, 'fiber' => $fiber];
            }
        }
    }
}
