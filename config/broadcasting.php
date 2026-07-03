<?php

return [

    'default' => env('BROADCAST_CONNECTION', env('BROADCAST_DRIVER', 'null')),

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            'client_options' => [],
        ],

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY', env('REVERB_APP_KEY', 'app-key')),
            'secret' => env('PUSHER_APP_SECRET', env('REVERB_APP_SECRET', 'app-secret')),
            'app_id' => env('PUSHER_APP_ID', env('REVERB_APP_ID', 'app-id')),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
                'host' => env('PUSHER_HOST', env('REVERB_HOST', '127.0.0.1')),
                'port' => env('PUSHER_PORT', env('REVERB_PORT', 6001)),
                'scheme' => env('PUSHER_SCHEME', env('REVERB_SCHEME', 'http')),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', env('REVERB_SCHEME', 'http')) === 'https',
            ],
            'client_options' => [],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
