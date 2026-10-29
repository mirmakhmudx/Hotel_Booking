<?php

declare(strict_types=1);

namespace App\Hotel\Domain;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The stay: check-in day included, check-out day excluded (half-open).
 *
 * Value object: immutable and defined only by its dates.
 * The overlap rule is written here and nowhere else.
 */
final class DateRange
{
    public readonly CarbonImmutable $checkIn;

    public readonly CarbonImmutable $checkOut;

    public function __construct(CarbonInterface|string $checkIn, CarbonInterface|string $checkOut)
    {
        $this->checkIn = CarbonImmutable::parse($checkIn)->startOfDay();
        $this->checkOut = CarbonImmutable::parse($checkOut)->startOfDay();
    }

    public function isValid(): bool
    {
        return $this->checkOut->greaterThan($this->checkIn);
    }

    /** Two stays overlap when they share at least one night. */
    public function overlaps(self $other): bool
    {
        return $this->checkIn->lessThan($other->checkOut)
            && $other->checkIn->lessThan($this->checkOut);
    }

    public function label(): string
    {
        return $this->checkIn->format('j F') . ' - ' . $this->checkOut->format('j F');
    }
}
