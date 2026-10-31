<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** The fourth border: the HTTP API may use the modules' public contracts, never their Internal code. */
final class HttpBorderTest extends TestCase
{
    public function test_the_http_api_uses_only_the_public_contracts_of_the_modules(): void
    {
        $root = dirname(__DIR__, 2);
        $violations = [];
        $count = 0;

        foreach (glob($root . '/app/Http/{*,*/*}.php', GLOB_BRACE) ?: [] as $file) {
            $count++;

            if (preg_match_all('/App\\\\Hotel\\\\\w+\\\\Internal\\\\\w+/', (string) file_get_contents($file), $matches) > 0) {
                foreach ($matches[0] as $name) {
                    $violations[] = substr($file, strlen($root) + 1) . ' uses ' . $name;
                }
            }
        }

        $this->assertGreaterThan(0, $count, 'No PHP files found in app/Http');
        $this->assertSame([], $violations);
    }
}
