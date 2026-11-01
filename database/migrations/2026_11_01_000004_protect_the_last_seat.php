<?php

use App\Hotel\Reservations\Internal\NoDoubleBookingConstraint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P09 · Protect the last seat.
 * 1. rooms.version: the counter the optimistic strategy compares and bumps.
 * 2. The exclusion constraint: the database refuses a second Confirmed booking on the same night.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedBigInteger('version')->default(0);
        });

        NoDoubleBookingConstraint::addOrExplain();
    }

    public function down(): void
    {
        NoDoubleBookingConstraint::drop();

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
