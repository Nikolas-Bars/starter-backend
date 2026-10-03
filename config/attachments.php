<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Вложения в чатах
    |--------------------------------------------------------------------------
    |
    | Файлы лежат на диске attachments (config/filesystems.php). Отдаёт их Caddy по подписанной
    | ссылке (deploy/Caddyfile, /api/files/*), PHP только проверяет подпись.
    |
    */

    'disk' => 'attachments',

    // Один файл до загрузки и обработки
    'max_file_bytes' => (int)env('ATTACHMENTS_MAX_FILE_BYTES', 50 * 1024 * 1024),

    // Все вложения вместе: при заполнении новые не принимаются
    'quota_bytes' => (int)env('ATTACHMENTS_QUOTA_BYTES', 10 * 1024 * 1024 * 1024),

    // Уведомление в Telegram (services.telegram) при заполнении; снова — только после падения ниже rearm_ratio
    'notify_ratio' => 0.9,
    'rearm_ratio'  => 0.85,

    'max_per_message' => 10,

    // Загруженное, но так и не отправленное вложение удаляется через столько часов
    'orphan_ttl_hours' => 24,

    'uploads_per_minute' => 30,

    'image' => [
        'max_side'      => 2560,
        'thumb_side'    => 480,
        'quality'       => 85,
        'thumb_quality' => 75,
    ],

    'video' => [
        // По длинной стороне: 1280×720 для горизонтального, 720×1280 для вертикального
        'max_side'   => 1280,
        'crf'        => 28,
        'audio_kbps' => 96,
        'threads'    => 2,
    ],

    'voice' => [
        'audio_kbps'     => 64,
        'waveform_peaks' => 64,
    ],

    'ffmpeg'  => env('FFMPEG_BINARY', 'ffmpeg'),
    'ffprobe' => env('FFPROBE_BINARY', 'ffprobe'),

    // Секунд на обработку одного файла
    'process_timeout' => 600,
];
