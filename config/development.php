<?php

return [
    'user' => [
        'email' => env('DEV_USER_EMAIL', 'gabrielsilva.contato9@gmail.com'),
        'password' => env('DEV_USER_PASSWORD'),
        'name' => env('DEV_USER_NAME', 'Gabriel'),
    ],
    'seed_demo_data' => (bool) env('DEV_SEED_DEMO_DATA', false),
];
