<?php

declare(strict_types=1);

namespace App\Modules\Call\Tasks;

use App\Modules\Call\WebSockets\MessageRouter;
use App\Services\RealtimeBus;
use App\Tasks\BaseTask;

final class NotifyCallFinishedTask extends BaseTask
{
    public function __construct(
        private readonly RealtimeBus $realtimeBus,
    ) {
    }

    /**
     * Звонок завершили не через сокет (например, отклонили из уведомления): сервер сигнализации
     * сам сообщит участникам и освободит соединения
     */
    public function run(int $callId): void
    {
        $this->realtimeBus->publish([], MessageRouter::CALL_FINISHED_EVENT, ['call_id' => $callId]);
    }
}
