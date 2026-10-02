<?php

declare(strict_types=1);

return [
    'fcm' => [
        // Ключ сервисного аккаунта Firebase (JSON из консоли: «Сервисные аккаунты» → «Создать закрытый ключ»).
        // Секрет: в git не попадает. Файла нет — push выключены, звонки работают как раньше
        'credentials' => env('FCM_CREDENTIALS', storage_path('app/firebase-credentials.json')),

        // Сколько секунд ждать ответа FCM
        'timeout' => (int)env('FCM_TIMEOUT', 5),
    ],

    // Сколько секунд push о звонке имеет смысл доставлять: позже вызов уже станет пропущенным
    'call_ttl' => (int)env('PUSH_CALL_TTL', 30),
];
