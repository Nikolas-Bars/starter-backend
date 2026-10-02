<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\Exceptions\ChatFolderNotFoundException;
use App\Modules\Chat\Tasks\DeleteChatFolderTask;
use App\Modules\Chat\Tasks\FindChatFolderTask;
use App\Modules\Chat\Tasks\NotifyChatFoldersChangedTask;
use App\Modules\User\Models\User;

final class DeleteChatFolderAction extends BaseAction
{
    public function __construct(
        private readonly FindChatFolderTask           $findChatFolderTask,
        private readonly DeleteChatFolderTask         $deleteChatFolderTask,
        private readonly NotifyChatFoldersChangedTask $notifyChatFoldersChangedTask,
    ) {
    }

    /**
     * @throws ChatFolderNotFoundException
     */
    public function run(User $user, int $folderId): void
    {
        $folder = $this->findChatFolderTask->run($folderId, $user->id) ?? throw new ChatFolderNotFoundException();

        $this->deleteChatFolderTask->run($folder);
        $this->notifyChatFoldersChangedTask->run($user->id);
    }
}
