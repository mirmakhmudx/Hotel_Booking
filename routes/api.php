<?php

use App\Http\Controllers\HotelApiController;
use Illuminate\Support\Facades\Route;

// P07: the hotel app on HTTP. No /api prefix: the paths are the ones in docs/openapi.yaml.
Route::get('/rooms/availability', [HotelApiController::class, 'availability']);
Route::post('/reservations', [HotelApiController::class, 'reserve']);
Route::delete('/reservations/{id}', [HotelApiController::class, 'cancel']);

// The contract is served by the app itself, so it cannot drift away from the file in docs/.
Route::get('/openapi.yaml', fn () => response(
    (string) file_get_contents(base_path('docs/openapi.yaml')),
    200,
    ['Content-Type' => 'application/yaml'],
));
