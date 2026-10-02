<?php

declare(strict_types=1);

return [
    // redis — события из API доходят до WebSocket-сервера через список в Redis;
    // array — события остаются в памяти процесса (тесты)
    'driver' => env('REALTIME_DRIVER', 'redis'),

    'redis' => [
        'connection' => env('REALTIME_REDIS_CONNECTION', 'default'),
        'key'        => 'realtime:events',
    ],

    // Сколько событий WebSocket-сервер забирает за один проход цикла
    'batch_size' => 200,
];
