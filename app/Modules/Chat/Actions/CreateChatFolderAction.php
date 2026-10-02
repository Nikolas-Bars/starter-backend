<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\Exceptions\ChatFolderLimitException;
use App\Modules\Chat\Models\ChatFolder;
use App\Modules\Chat\Tasks\CountChatFoldersTask;
use App\Modules\Chat\Tasks\CreateChatFolderTask;
use App\Modules\Chat\Tasks\NotifyChatFoldersChangedTask;
use App\Modules\User\Models\User;

final class CreateChatFolderAction extends BaseAction
{
    public const MAX_FOLDERS = 20;

    public function __construct(
        private readonly CountChatFoldersTask         $countChatFoldersTask,
        private readonly CreateChatFolderTask         $createChatFolderTask,
        private readonly NotifyChatFoldersChangedTask $notifyChatFoldersChangedTask,
    ) {
    }

    /**
     * @throws ChatFolderLimitException
     */
    public function run(User $user, string $name): ChatFolder
    {
        if ($this->countChatFoldersTask->run($user->id) >= self::MAX_FOLDERS) {
            throw new ChatFolderLimitException();
        }

        $folder = $this->createChatFolderTask->run($user->id, $name);
        $this->notifyChatFoldersChangedTask->run($user->id);

        return $folder;
    }
}
