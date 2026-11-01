<?php

declare(strict_types=1);

namespace App\Hotel\Reservations\Internal;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The last line of defence (R3, S2): PostgreSQL itself refuses two Confirmed bookings of one room
 * that share a night. daterange(check_in, check_out, '[)') is the same half-open stay as DateRange.
 * One place for the SQL: the migration, the race command and the tests all use it.
 */
final class NoDoubleBookingConstraint
{
    public const NAME = 'reservations_no_double_booking';

    /** SQLSTATE exclusion_violation */
    public const SQLSTATE = '23P01';

    public static function add(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement(sprintf(
            "ALTER TABLE reservations ADD CONSTRAINT %s EXCLUDE USING gist (room_id WITH =, daterange(check_in, check_out, '[)') WITH &&) WHERE (status = 'Confirmed')",
            self::NAME,
        ));
    }

    public static function drop(): void
    {
        DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS ' . self::NAME);
    }

    public static function exists(): bool
    {
        return DB::table('pg_constraint')->where('conname', self::NAME)->exists();
    }

    /** Pairs of Confirmed bookings that already share a night. The constraint cannot be added while any exist. */
    public static function existingConflicts(): int
    {
        return (int) DB::scalar(<<<'SQL'
            SELECT COUNT(*) FROM reservations a
            JOIN reservations b
              ON a.id < b.id AND a.room_id = b.room_id
             AND a.status = 'Confirmed' AND b.status = 'Confirmed'
             AND a.check_in < b.check_out AND b.check_in < a.check_out
            SQL);
    }

    public static function addOrExplain(): void
    {
        $conflicts = self::existingConflicts();

        if ($conflicts > 0) {
            throw new RuntimeException(
                "Cannot add the constraint: {$conflicts} pair(s) of Confirmed reservations already share a night "
                . '(left over from the version 1 race demo). Run `php artisan hotel:demo` (it clears all reservations) '
                . 'or cancel one booking of each pair, then migrate again.'
            );
        }

        self::add();
    }
}
