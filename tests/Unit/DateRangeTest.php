<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Hotel\Reservations\DateRange;
use PHPUnit\Framework\TestCase;

final class DateRangeTest extends TestCase
{
    public function test_check_out_must_be_after_check_in(): void
    {
        $this->assertTrue((new DateRange('2026-11-12', '2026-11-14'))->isValid());
        $this->assertFalse((new DateRange('2026-11-15', '2026-11-15'))->isValid());
        $this->assertFalse((new DateRange('2026-11-16', '2026-11-14'))->isValid());
    }

    public function test_stays_sharing_a_night_overlap(): void
    {
        $maya = new DateRange('2026-11-12', '2026-11-14');
        $omar = new DateRange('2026-11-13', '2026-11-15');

        $this->assertTrue($maya->overlaps($omar));
        $this->assertTrue($omar->overlaps($maya));
    }

    public function test_check_out_day_is_free(): void
    {
        $maya = new DateRange('2026-11-12', '2026-11-14');
        $nora = new DateRange('2026-11-14', '2026-11-16');

        $this->assertFalse($maya->overlaps($nora));
        $this->assertFalse($nora->overlaps($maya));
    }
}
