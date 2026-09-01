<?php

return [
    'url' => env('HOSPITAL_API_URL', ''),
    'token' => env('HOSPITAL_API_TOKEN', ''),
    'timeout' => (int) env('HOSPITAL_API_TIMEOUT', 5),
];
