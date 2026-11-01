<?php

return [
    // P08: HOTEL_CACHE=false switches the search cache off, to measure the search without it (Step 1).
    'cache' => (bool) env('HOTEL_CACHE', true),

    // P09: unprotected | atomic | optimistic | lock. The default is the short row-lock transaction.
    'concurrency' => env('HOTEL_CONCURRENCY', 'lock'),
];
