<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Api\KeyValueStore;

/** The three commands on an array. It remembers the last time to live, so a test can check it. */
final class InMemoryKeyValueStore implements KeyValueStore
{
    /** @var array<string, string> */
    private array $values = [];

    public ?int $lastTtl = null;

    public function get(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, string $value, int $ttlSeconds): void
    {
        $this->values[$key] = $value;
        $this->lastTtl = $ttlSeconds;
    }

    public function increment(string $key): void
    {
        $this->values[$key] = (string) ((int) ($this->values[$key] ?? '0') + 1);
    }
}
