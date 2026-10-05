<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Repositories\ChatMessageRepository;
use App\Tasks\BaseTask;

final class FindChatMessageWithAttachmentsTask extends BaseTask
{
    public function __construct(
        private readonly ChatMessageRepository $repository,
    ) {
    }

    /**
     * Сообщение из любого чата — доступ проверяет вызывающий
     */
    public function run(int $messageId): ?ChatMessage
    {
        return $this->repository->findWithAttachments($messageId);
    }
}
