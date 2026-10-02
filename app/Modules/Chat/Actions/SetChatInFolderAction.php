<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\Exceptions\ChatFolderNotFoundException;
use App\Modules\Chat\Exceptions\ChatNotFoundException;
use App\Modules\Chat\Models\ChatFolder;
use App\Modules\Chat\Tasks\FindChatFolderTask;
use App\Modules\Chat\Tasks\FindChatMemberTask;
use App\Modules\Chat\Tasks\NotifyChatFoldersChangedTask;
use App\Modules\Chat\Tasks\SetChatInFolderTask;
use App\Modules\User\Models\User;

final class SetChatInFolderAction extends BaseAction
{
    public function __construct(
        private readonly FindChatFolderTask           $findChatFolderTask,
        private readonly FindChatMemberTask           $findChatMemberTask,
        private readonly SetChatInFolderTask          $setChatInFolderTask,
        private readonly NotifyChatFoldersChangedTask $notifyChatFoldersChangedTask,
    ) {
    }

    /**
     * Добавляет свой чат в свою папку ($included = true) или убирает оттуда.
     * Возвращает папку в новом состоянии.
     *
     * @throws ChatFolderNotFoundException
     * @throws ChatNotFoundException
     */
    public function run(User $user, int $folderId, int $chatId, bool $included): ChatFolder
    {
        $folder = $this->findChatFolderTask->run($folderId, $user->id) ?? throw new ChatFolderNotFoundException();

        if ($this->findChatMemberTask->run($chatId, $user->id) === null) {
            throw new ChatNotFoundException();
        }

        $folder = $this->setChatInFolderTask->run($folder, $chatId, $included);
        $this->notifyChatFoldersChangedTask->run($user->id);

        return $folder;
    }
}
