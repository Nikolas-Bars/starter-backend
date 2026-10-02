<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatFolder;
use App\Modules\Chat\Repositories\ChatFolderRepository;
use App\Tasks\BaseTask;

final class SetChatInFolderTask extends BaseTask
{
    public function __construct(
        private readonly ChatFolderRepository $repository,
    ) {
    }

    /**
     * Добавляет чат в папку или убирает из неё; повтор ничего не меняет.
     * Возвращает папку с перечитанными чатами.
     */
    public function run(ChatFolder $folder, int $chatId, bool $included): ChatFolder
    {
        if ($included) {
            $this->repository->attachChat($folder, $chatId);
        } else {
            $this->repository->detachChat($folder, $chatId);
        }

        return $this->repository->reloadState($folder);
    }
}
