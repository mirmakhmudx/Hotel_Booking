<?php

declare(strict_types=1);

namespace App\Api;

use InvalidArgumentException;

/**
 * The four counters of GET /stats. `php artisan serve` starts a new PHP process for every request,
 * so the counters live in the cache store, not in memory. They only grow (until cache:clear).
 */
final class Stats
{
    public const NAMES = ['searches', 'databaseQueries', 'cacheHits', 'cacheMisses'];

    public function __construct(private readonly KeyValueStore $store)
    {
    }

    public function add(string $name): void
    {
        if (! in_array($name, self::NAMES, true)) {
            throw new InvalidArgumentException('Unknown counter ' . $name);
        }

        $this->store->increment('stats:' . $name);
    }

    /** @return array<string, int> */
    public function asArray(): array
    {
        $counts = [];

        foreach (self::NAMES as $name) {
            $counts[$name] = (int) ($this->store->get('stats:' . $name) ?? 0);
        }

        return $counts;
    }
}
