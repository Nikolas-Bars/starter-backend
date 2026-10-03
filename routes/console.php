<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Истёкшие токены доступа удаляются раз в сутки (срок жизни — SANCTUM_TOKEN_EXPIRATION)
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Файлы, которые загрузили, но так и не отправили, удаляются через сутки
Schedule::command('attachments:prune')->hourly();
