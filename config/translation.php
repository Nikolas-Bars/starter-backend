<?php

declare(strict_types=1);

// Автоперевод сообщений чата нейросетью. Ключи читает только App\Services\SecretReader: в проде это
// файлы в deploy/secrets/ (*_API_KEY_FILE), локально можно задать сам ключ (*_API_KEY).
// Ни один ключ не уходит клиентам: у фронтенда и приложения нет переменных для них. Нет ключа — перевод выключен
return [
    // openai | anthropic
    'provider' => env('TRANSLATION_PROVIDER', 'openai'),

    // Сколько предыдущих сообщений чата уходит нейросети как контекст разговора
    'context_messages' => (int) env('TRANSLATION_CONTEXT_MESSAGES', 10),

    // Секунды на ответ провайдера; дольше — задача в очереди повторится позже
    'timeout' => (int) env('TRANSLATION_TIMEOUT', 30),

    // Сколько секунд собеседник с другим языком ждёт новое сообщение вместе с переводом; дольше —
    // получит оригинал, а перевод придёт следом
    'hold_seconds' => (int) env('TRANSLATION_HOLD_SECONDS', 15),

    'providers' => [
        'openai' => [
            'model'        => env('OPENAI_TRANSLATION_MODEL', 'gpt-4o-mini'),
            'base_url'     => 'https://api.openai.com/v1',
            'api_key'      => env('OPENAI_API_KEY'),
            'api_key_file' => env('OPENAI_API_KEY_FILE'),
        ],
        'anthropic' => [
            'model'        => env('ANTHROPIC_TRANSLATION_MODEL', 'claude-haiku-4-5'),
            'base_url'     => 'https://api.anthropic.com/v1',
            'api_key'      => env('ANTHROPIC_API_KEY'),
            'api_key_file' => env('ANTHROPIC_API_KEY_FILE'),
        ],
    ],
];
