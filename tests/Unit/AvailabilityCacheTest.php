<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Api\AvailabilityCache;
use App\Api\Stats;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryKeyValueStore;

/** The key, the version and the TTL, without Laravel and without a cache server. */
final class AvailabilityCacheTest extends TestCase
{
    public function test_the_key_holds_the_version_and_the_stay(): void
    {
        $cache = new AvailabilityCache(new InMemoryKeyValueStore());

        $this->assertSame('availability:v0:2026-11-12:2026-11-14', $cache->key('2026-11-12', '2026-11-14'));
    }

    public function test_a_copy_is_kept_for_30_seconds(): void
    {
        $store = new InMemoryKeyValueStore();
        $cache = new AvailabilityCache($store);
        $key = $cache->key('2026-11-12', '2026-11-14');

        $this->assertNull($cache->get($key));

        $cache->set($key, 'R7,R8');

        $this->assertSame('R7,R8', $cache->get($key));
        $this->assertSame(30, $store->lastTtl);
    }

    public function test_invalidating_makes_the_old_copy_unreachable(): void
    {
        $cache = new AvailabilityCache(new InMemoryKeyValueStore());
        $oldKey = $cache->key('2026-11-12', '2026-11-14');
        $cache->set($oldKey, 'R7,R8');

        $cache->invalidate();
        $newKey = $cache->key('2026-11-12', '2026-11-14');

        $this->assertSame('availability:v1:2026-11-12:2026-11-14', $newKey);
        $this->assertNull($cache->get($newKey));
    }

    public function test_the_counters_start_at_zero_and_only_grow(): void
    {
        $stats = new Stats(new InMemoryKeyValueStore());

        $this->assertSame(['searches' => 0, 'databaseQueries' => 0, 'cacheHits' => 0, 'cacheMisses' => 0], $stats->asArray());

        $stats->add('searches');
        $stats->add('searches');
        $stats->add('databaseQueries');

        $this->assertSame(['searches' => 2, 'databaseQueries' => 1, 'cacheHits' => 0, 'cacheMisses' => 0], $stats->asArray());
    }
}
