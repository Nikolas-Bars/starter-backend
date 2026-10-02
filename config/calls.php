<?php

declare(strict_types=1);

$csv = static fn(string $value): array => \array_values(\array_filter(\array_map('trim', \explode(',', $value))));

$turnUrls = $csv((string)env('CALL_TURN_URLS', ''));

$turnSecret = (string)env('CALL_TURN_SECRET', '');

$wsOrigins = $csv((string)env('CALL_WS_ALLOWED_ORIGINS', ''));

return [
    // Сколько секунд звонит вызов, прежде чем стать пропущенным
    'ring_timeout' => (int)env('CALL_RING_TIMEOUT', 30),

    // ICE-серверы для RTCPeerConnection. STUN по умолчанию публичный и бесплатный;
    // TURN (например, свой coturn) подключается через .env, когда понадобится.
    // С CALL_TURN_SECRET постоянный логин не раздаётся: каждый пользователь получает временный (см. turn)
    'ice_servers' => \array_values(\array_filter([
        ['urls' => $csv((string)env('CALL_STUN_URLS', 'stun:stun.l.google.com:19302'))],
        $turnUrls === [] || $turnSecret !== '' ? null : [
            'urls'       => $turnUrls,
            'username'   => (string)env('CALL_TURN_USERNAME', ''),
            'credential' => (string)env('CALL_TURN_CREDENTIAL', ''),
        ],
    ], static fn(?array $server): bool => $server !== null && $server['urls'] !== [])),

    // Временные логины TURN по общему секрету (coturn: --use-auth-secret --static-auth-secret=...)
    'turn' => [
        'urls'   => $turnUrls,
        'secret' => $turnSecret,
        // Сколько секунд действует выданный логин; разговор, начатый до истечения, не прервётся
        'ttl' => (int)env('CALL_TURN_TTL', 6 * 3600),
    ],

    'links' => [
        // Сколько минут действует вход гостя, пришедшего по ссылке для звонка
        'guest_token_ttl' => (int)env('CALL_GUEST_TOKEN_TTL', 12 * 60),

        // Попыток войти гостем в минуту с одного IP
        'join_attempts_per_minute' => (int)env('CALL_LINK_JOIN_ATTEMPTS', 10),
    ],

    'websocket' => [
        'host' => env('CALL_WS_HOST', '0.0.0.0'),
        'port' => (int)env('CALL_WS_PORT', 8091),

        // Браузер присылает Origin при подключении: чужим сайтам открыть сокет от имени пользователя нельзя.
        // Не задано — те же адреса, что и для CORS; пустой итоговый список отключает проверку
        'allowed_origins' => $wsOrigins !== [] ? $wsOrigins : $csv((string)env('CORS_ALLOWED_ORIGINS', '')),

        // Сервер пингует молчащие соединения и закрывает те, что не отвечают
        'ping_interval' => 25,
        'idle_timeout'  => 60,

        // Сколько секунд разговор ждёт участника, у которого оборвалось соединение
        'resume_timeout' => (int)env('CALL_RESUME_TIMEOUT', 20),

        // Сколько секунд действует одноразовый билет на подключение к сокету
        'ticket_ttl' => 30,

        // Самое большое сообщение — SDP-описание соединения, обычно несколько килобайт
        'max_message_size' => 256 * 1024,
    ],
];
