<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\DTO\HeldChatEventDTO;
use App\Modules\Chat\Tasks\DeliverHeldChatEventTask;

final class DeliverHeldChatEventAction extends BaseAction
{
    public function __construct(
        private readonly DeliverHeldChatEventTask $deliverHeldChatEventTask,
    ) {
    }

    /**
     * Перевод не успел: собеседники получают сообщение оригиналом, перевод придёт событием
     * chat.message_translated. Если перевод уже отдал событие — ничего не делает.
     */
    public function run(int $messageId, HeldChatEventDTO $held): void
    {
        $this->deliverHeldChatEventTask->run($held, $messageId);
    }
}
