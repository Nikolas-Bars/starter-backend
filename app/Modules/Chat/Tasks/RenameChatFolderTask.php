<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatFolder;
use App\Modules\Chat\Repositories\ChatFolderRepository;
use App\Tasks\BaseTask;

final class RenameChatFolderTask extends BaseTask
{
    public function __construct(
        private readonly ChatFolderRepository $repository,
    ) {
    }

    public function run(ChatFolder $folder, string $name): void
    {
        $this->repository->rename($folder, $name);
    }
}
