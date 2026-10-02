<?php

declare(strict_types=1);

namespace App\Modules\Call\Jobs;

use App\Jobs\BaseJob;
use App\Modules\Call\Actions\SendCallPushAction;
use App\Modules\Call\Enums\CallPushEventEnum;

/**
 * Сервер сигнализации — один цикл на всех: запрос к FCM уходит в очередь, чтобы не задерживать звонки
 */
final class SendCallPushJob extends BaseJob
{
    /**
     * Повторять бессмысленно: пока push дойдёт, вызов уже перестанет звонить
     */
    public int $tries = 1;

    public function __construct(
        public readonly int               $callId,
        public readonly CallPushEventEnum $event,
    ) {
    }

    public function handle(SendCallPushAction $action): void
    {
        $action->run($this->callId, $this->event);
    }
}
