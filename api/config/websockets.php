<?php

return [
    'dashboard' => [
        'port' => env('PUSHER_PORT', 6001),
    ],

    'apps' => [
        [
            'id'                    => env('PUSHER_APP_ID', 'nexachat'),
            'name'                  => env('APP_NAME', 'NexaChat'),
            'key'                   => env('PUSHER_APP_KEY', 'nexachat_key'),
            'secret'                => env('PUSHER_APP_SECRET', 'nexachat_secret'),
            'capacity'              => null,
            'enable_client_messages'=> true,  // Permet les whispers (typing)
            'enable_statistics'     => true,
        ],
    ],

    'app_provider' => BeyondCode\LaravelWebSockets\Apps\ConfigAppProvider::class,

    'allowed_origins' => ['*'],

    'max_request_size_in_kb' => 250,

    'path' => 'laravel-websockets',

    'middleware' => [
        'web',
        \BeyondCode\LaravelWebSockets\Dashboard\Http\Middleware\Authorize::class,
    ],

    'statistics' => [
        'model'                  => \BeyondCode\LaravelWebSockets\Statistics\Models\WebSocketsStatisticsEntry::class,
        'interval_in_seconds'    => 60,
        'delete_statistics_older_than_days' => 7,
    ],

    'ssl' => [
        'local_cert'  => null,
        'capath'      => null,
        'local_pk'    => null,
        'passphrase'  => null,
        'verify_peer' => true,
        'allow_self_signed' => false,
    ],

    'channel_manager' => \BeyondCode\LaravelWebSockets\WebSockets\Channels\ChannelManagers\ArrayChannelManager::class,
];
