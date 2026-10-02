<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Repositories\ChatFolderRepository;
use App\Tasks\BaseTask;

final class CountChatFoldersTask extends BaseTask
{
    public function __construct(
        private readonly ChatFolderRepository $repository,
    ) {
    }

    public function run(int $userId): int
    {
        return $this->repository->countForUser($userId);
    }
}
