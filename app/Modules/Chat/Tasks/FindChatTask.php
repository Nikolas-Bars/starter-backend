<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Repositories\ChatRepository;
use App\Tasks\BaseTask;

final class FindChatTask extends BaseTask
{
    public function __construct(
        private readonly ChatRepository $repository,
    ) {
    }

    public function run(int $chatId): ?Chat
    {
        return $this->repository->findById($chatId);
    }
}
