<?php

return [
    'web_url' => env('HOSPITAL_URL', ''),
    'url' => env('HOSPITAL_API_URL', ''),
    'token' => env('HOSPITAL_API_TOKEN', ''),
    'timeout' => (int) env('HOSPITAL_API_TIMEOUT', 5),
    'delegated_auth' => [
        'secret' => env('HOSPITAL_DELEGATED_AUTH_SECRET', ''),
        'ttl' => (int) env('HOSPITAL_DELEGATED_AUTH_TTL', 60),
        'audience' => 'cmg-warehouse',
        'clock_skew' => 5,
    ],
];
