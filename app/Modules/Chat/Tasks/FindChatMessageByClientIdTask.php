<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Repositories\ChatMessageRepository;
use App\Tasks\BaseTask;

final class FindChatMessageByClientIdTask extends BaseTask
{
    public function __construct(
        private readonly ChatMessageRepository $repository,
    ) {
    }

    public function run(int $userId, string $clientId): ?ChatMessage
    {
        return $this->repository->findByClientId($userId, $clientId);
    }
}
