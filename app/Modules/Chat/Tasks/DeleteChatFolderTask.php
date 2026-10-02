<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatFolder;
use App\Modules\Chat\Repositories\ChatFolderRepository;
use App\Tasks\BaseTask;

final class DeleteChatFolderTask extends BaseTask
{
    public function __construct(
        private readonly ChatFolderRepository $repository,
    ) {
    }

    /**
     * Сами чаты остаются: удаляется только папка и её связи с ними
     */
    public function run(ChatFolder $folder): void
    {
        $this->repository->delete($folder);
    }
}
