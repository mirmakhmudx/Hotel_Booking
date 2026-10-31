<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** The fourth border: the HTTP API and the cache code may use the modules' public contracts, never their Internal code. */
final class HttpBorderTest extends TestCase
{
    public function test_the_http_api_uses_only_the_public_contracts_of_the_modules(): void
    {
        $root = dirname(__DIR__, 2);
        $files = array_merge(
            glob($root . '/app/Http/{*,*/*}.php', GLOB_BRACE) ?: [],
            glob($root . '/app/Api/*.php') ?: [],
        );
        $violations = [];

        foreach ($files as $file) {
            if (preg_match_all('/App\\\\Hotel\\\\\w+\\\\Internal\\\\\w+/', (string) file_get_contents($file), $matches) > 0) {
                foreach ($matches[0] as $name) {
                    $violations[] = substr($file, strlen($root) + 1) . ' uses ' . $name;
                }
            }
        }

        $this->assertGreaterThan(0, count($files), 'No PHP files found in app/Http and app/Api');
        $this->assertSame([], $violations);
    }
}
