<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id(); // reservation code "R101" is built from this id
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->date('check_in');
            $table->date('check_out');
            $table->string('status')->default('Confirmed');
            $table->timestamps();

            $table->index(['room_id', 'status']);
        });

        // R3, second half: check-out must be after check-in, enforced by the database too.
        // The "no two Confirmed bookings on the same night" rule is NOT enforced here on purpose:
        // version 1 shows the race condition, P09 fixes it in the database.
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_dates_valid CHECK (check_out > check_in)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
