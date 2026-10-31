<?php

declare(strict_types=1);

namespace App\Api;

/**
 * The cache for the free-room search (P08): cache-aside, with a TTL and a version.
 * PostgreSQL is the source of truth. The cache keeps copies of search answers for 30 seconds:
 * the free room numbers for one stay, such as "R7,R8". Booking and cancelling never read the cache.
 */
final class AvailabilityCache
{
    public const TTL_SECONDS = 30;

    public const VERSION_KEY = 'availability:version';

    public function __construct(private readonly KeyValueStore $store)
    {
    }

    /** The version is part of the key, so raising it makes every older copy unreachable. */
    public function key(string $checkIn, string $checkOut): string
    {
        $version = $this->store->get(self::VERSION_KEY) ?? '0';

        return 'availability:v' . $version . ':' . $checkIn . ':' . $checkOut;
    }

    public function get(string $key): ?string
    {
        return $this->store->get($key);
    }

    public function set(string $key, string $roomNumbers): void
    {
        $this->store->set($key, $roomNumbers, self::TTL_SECONDS);
    }

    /** A booking or a cancellation changed what is free: every cached search is now out of date. */
    public function invalidate(): void
    {
        $this->store->increment(self::VERSION_KEY);
    }
}
