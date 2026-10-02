<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\Exceptions\ChatFolderNotFoundException;
use App\Modules\Chat\Models\ChatFolder;
use App\Modules\Chat\Tasks\FindChatFolderTask;
use App\Modules\Chat\Tasks\NotifyChatFoldersChangedTask;
use App\Modules\Chat\Tasks\RenameChatFolderTask;
use App\Modules\User\Models\User;

final class RenameChatFolderAction extends BaseAction
{
    public function __construct(
        private readonly FindChatFolderTask           $findChatFolderTask,
        private readonly RenameChatFolderTask         $renameChatFolderTask,
        private readonly NotifyChatFoldersChangedTask $notifyChatFoldersChangedTask,
    ) {
    }

    /**
     * @throws ChatFolderNotFoundException
     */
    public function run(User $user, int $folderId, string $name): ChatFolder
    {
        $folder = $this->findChatFolderTask->run($folderId, $user->id) ?? throw new ChatFolderNotFoundException();

        $this->renameChatFolderTask->run($folder, $name);
        $this->notifyChatFoldersChangedTask->run($user->id);

        return $folder;
    }
}
