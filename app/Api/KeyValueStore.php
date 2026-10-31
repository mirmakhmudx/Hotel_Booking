<?php

declare(strict_types=1);

namespace App\Api;

/**
 * The three commands the cache and the counters need: GET, SET with a time to live, and INCR.
 * The app gives it Laravel's cache (file here, Redis in production); the tests can give it an array.
 */
interface KeyValueStore
{
    public function get(string $key): ?string;

    public function set(string $key, string $value, int $ttlSeconds): void;

    public function increment(string $key): void;
}
