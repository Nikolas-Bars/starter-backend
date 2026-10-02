<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatFolder;
use App\Modules\Chat\Repositories\ChatFolderRepository;
use App\Tasks\BaseTask;
use Illuminate\Database\Eloquent\Collection;

final class ListChatFoldersTask extends BaseTask
{
    public function __construct(
        private readonly ChatFolderRepository $repository,
    ) {
    }

    /**
     * @return Collection<int, ChatFolder>
     */
    public function run(int $userId): Collection
    {
        return $this->repository->listForUser($userId);
    }
}
