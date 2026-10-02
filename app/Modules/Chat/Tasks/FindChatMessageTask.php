<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Repositories\ChatMessageRepository;
use App\Tasks\BaseTask;

final class FindChatMessageTask extends BaseTask
{
    public function __construct(
        private readonly ChatMessageRepository $repository,
    ) {
    }

    /**
     * Сообщение, только если оно из этого чата
     */
    public function run(int $chatId, int $messageId): ?ChatMessage
    {
        return $this->repository->findInChat($chatId, $messageId);
    }
}
