<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Hotel\Rooms\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['R7', 'Standard'], ['R8', 'Deluxe']] as [$number, $type]) {
            Room::query()->updateOrCreate(['number' => $number], ['type' => $type]);
        }
    }
}
