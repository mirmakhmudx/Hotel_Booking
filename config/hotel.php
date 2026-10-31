<?php

return [
    // P08: HOTEL_CACHE=false switches the search cache off, to measure the search without it (Step 1).
    'cache' => (bool) env('HOTEL_CACHE', true),
];
