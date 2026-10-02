<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Services\RealtimeBus;
use App\Tasks\BaseTask;

final class PublishChatEventTask extends BaseTask
{
    public function __construct(
        private readonly RealtimeBus $realtimeBus,
    ) {
    }

    /**
     * Событие уходит во все вкладки участников, что сейчас в сети. Публиковать после
     * фиксации транзакции: иначе клиент может запросить данные, которых ещё нет.
     *
     * @param list<int>            $userIds
     * @param array<string, mixed> $data
     */
    public function run(array $userIds, string $type, array $data): void
    {
        $this->realtimeBus->publish($userIds, $type, $data);
    }
}
