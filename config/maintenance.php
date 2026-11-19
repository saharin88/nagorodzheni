<?php

return [
    'artisan_route' => [
        'enabled' => env('MAINTENANCE_ARTISAN_ROUTE_ENABLED', false),
        'token' => env('MAINTENANCE_ARTISAN_ROUTE_TOKEN', ''),
        'throttle' => env('MAINTENANCE_ARTISAN_ROUTE_THROTTLE', '6,1'),
        'commands' => [
            'optimize',
        ],
    ],
];
