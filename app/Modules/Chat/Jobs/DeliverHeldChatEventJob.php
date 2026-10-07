<?php

declare(strict_types=1);

namespace App\Modules\Chat\Jobs;

use App\Jobs\BaseJob;
use App\Modules\Chat\Actions\DeliverHeldChatEventAction;
use App\Modules\Chat\DTO\HeldChatEventDTO;

/**
 * Таймер для сообщения, которое ждёт перевода: не дождалось за translation.hold_seconds — уходит оригиналом
 */
final class DeliverHeldChatEventJob extends BaseJob
{
    public int $tries = 1;

    public function __construct(
        public readonly int $messageId,
        public readonly HeldChatEventDTO $held,
    ) {
    }

    public function handle(DeliverHeldChatEventAction $action): void
    {
        $action->run($this->messageId, $this->held);
    }
}
