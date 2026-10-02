<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatFolder;
use App\Modules\Chat\Repositories\ChatFolderRepository;
use App\Tasks\BaseTask;

final class FindChatFolderTask extends BaseTask
{
    public function __construct(
        private readonly ChatFolderRepository $repository,
    ) {
    }

    /**
     * Папка, только если она принадлежит пользователю
     */
    public function run(int $folderId, int $userId): ?ChatFolder
    {
        return $this->repository->findForUser($folderId, $userId);
    }
}
