<?php

declare(strict_types=1);

namespace Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The three borders between the modules, checked by reading the code itself.
 * A failure names the file and the class it must not use.
 */
final class ArchitectureTest extends TestCase
{
    public function test_reservations_uses_only_the_public_contracts_of_other_modules(): void
    {
        $violations = [];

        foreach ($this->usesIn('Reservations') as $use) {
            if ($use['module'] !== 'Reservations' && $use['internal']) {
                $violations[] = $use['file'] . ' uses ' . $use['name'] . ' (internal code of ' . $use['module'] . ')';
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_rooms_does_not_depend_on_any_other_module(): void
    {
        $violations = [];

        foreach ($this->usesIn('Rooms') as $use) {
            if ($use['module'] !== 'Rooms') {
                $violations[] = $use['file'] . ' uses ' . $use['name'];
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_payments_and_notifications_do_not_depend_on_reservations(): void
    {
        $violations = [];

        foreach (['Payments', 'Notifications'] as $module) {
            foreach ($this->usesIn($module) as $use) {
                if ($use['module'] === 'Reservations') {
                    $violations[] = $use['file'] . ' uses ' . $use['name'];
                }
            }
        }

        $this->assertSame([], $violations);
    }

    /**
     * Every App\Hotel\<Module>\... name that one module's files mention.
     *
     * @return list<array{file: string, module: string, internal: bool, name: string}>
     */
    private function usesIn(string $module): array
    {
        $root = dirname(__DIR__, 2);
        $folder = $root . '/app/Hotel/' . $module;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS));
        $uses = [];
        $count = 0;

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $count++;
            $shown = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $found = preg_match_all(
                '/App\\\\Hotel\\\\(Rooms|Reservations|Payments|Notifications)(\\\\Internal)?\\\\(\w+)/',
                (string) file_get_contents($file->getPathname()),
                $matches,
                PREG_SET_ORDER,
            );

            foreach ($found === false ? [] : $matches as $match) {
                $uses[] = [
                    'file' => $shown,
                    'module' => $match[1],
                    'internal' => ($match[2] ?? '') !== '',
                    'name' => $match[0],
                ];
            }
        }

        $this->assertGreaterThan(0, $count, 'No PHP files found for the module ' . $module);

        return $uses;
    }
}
