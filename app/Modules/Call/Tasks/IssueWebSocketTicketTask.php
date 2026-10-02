<?php

declare(strict_types=1);

namespace App\Modules\Call\Tasks;

use App\Tasks\BaseTask;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class IssueWebSocketTicketTask extends BaseTask
{
    public const CACHE_PREFIX = 'calls:ws-ticket:';

    /**
     * Кэш должен быть общим для API и сервера сигнализации (в docker-compose — Redis).
     */
    public function run(int $userId, int $ttlSeconds): string
    {
        $ticket = Str::random(48);

        Cache::put(self::CACHE_PREFIX . $ticket, $userId, $ttlSeconds);

        return $ticket;
    }
}
