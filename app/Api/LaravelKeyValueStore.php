<?php

declare(strict_types=1);

namespace App\Api;

use Illuminate\Contracts\Cache\Repository;

/** The three commands, sent to Laravel's cache. CACHE_STORE in .env decides: file, database or redis. */
final class LaravelKeyValueStore implements KeyValueStore
{
    public function __construct(private readonly Repository $cache)
    {
    }

    public function get(string $key): ?string
    {
        $value = $this->cache->get($key);

        return $value === null ? null : (string) $value;
    }

    public function set(string $key, string $value, int $ttlSeconds): void
    {
        $this->cache->put($key, $value, $ttlSeconds);
    }

    public function increment(string $key): void
    {
        if ($this->cache->get($key) === null) {
            $this->cache->forever($key, 0); // INCR on a missing key: start from 0
        }

        $this->cache->increment($key);
    }
}
