<?php

declare(strict_types=1);

namespace App\Modules\Call\Tasks;

use App\Tasks\BaseTask;
use Illuminate\Support\Facades\Cache;

final class ConsumeWebSocketTicketTask extends BaseTask
{
    /**
     * @return int|null Чей билет; null — билета нет, он истёк или уже использован
     */
    public function run(string $ticket): ?int
    {
        if ($ticket === '') {
            return null;
        }

        $userId = Cache::pull(IssueWebSocketTicketTask::CACHE_PREFIX . $ticket);

        return \is_int($userId) ? $userId : null;
    }
}
