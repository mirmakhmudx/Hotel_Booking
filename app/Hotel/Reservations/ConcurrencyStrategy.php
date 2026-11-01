<?php

declare(strict_types=1);

namespace App\Hotel\Reservations;

/**
 * How the store protects "check, then save" when two requests arrive at the same moment (P09).
 *
 * Unprotected  version 1: check, gap, save. Kept only to show the bug.
 * AtomicInsert one INSERT ... WHERE NOT EXISTS: check and save are one statement.
 * Optimistic   read the room's version, check, then save only if the version is still the same.
 * RowLock      lock the room's row in a short transaction, check, save, release.
 */
enum ConcurrencyStrategy: string
{
    case Unprotected = 'unprotected';
    case AtomicInsert = 'atomic';
    case Optimistic = 'optimistic';
    case RowLock = 'lock';
}
