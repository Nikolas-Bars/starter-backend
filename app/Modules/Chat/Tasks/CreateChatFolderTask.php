<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatFolder;
use App\Modules\Chat\Repositories\ChatFolderRepository;
use App\Tasks\BaseTask;

final class CreateChatFolderTask extends BaseTask
{
    public function __construct(
        private readonly ChatFolderRepository $repository,
    ) {
    }

    /**
     * Пустая папка — сразу в том виде, в каком её отдаёт API
     */
    public function run(int $userId, string $name): ChatFolder
    {
        return $this->repository->reloadState($this->repository->store($userId, $name));
    }
}
