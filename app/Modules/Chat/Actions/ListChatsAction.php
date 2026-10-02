<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\Exceptions\ChatFolderNotFoundException;
use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Tasks\FindChatFolderTask;
use App\Modules\Chat\Tasks\ListMemberChatsTask;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListChatsAction extends BaseAction
{
    public function __construct(
        private readonly ListMemberChatsTask $listMemberChatsTask,
        private readonly FindChatFolderTask  $findChatFolderTask,
    ) {
    }

    /**
     * Чат, открытый без единого сообщения, в список не попадает — ни у того, кто открыл, ни у собеседника.
     * С $folderId — только чаты этой папки пользователя.
     *
     *
     * @throws ChatFolderNotFoundException
     * @return LengthAwarePaginator<int, Chat>
     */
    public function run(User $user, ?int $folderId, int $perPage): LengthAwarePaginator
    {
        if ($folderId !== null && $this->findChatFolderTask->run($folderId, $user->id) === null) {
            throw new ChatFolderNotFoundException();
        }

        return $this->listMemberChatsTask->run($user->id, $folderId, $perPage);
    }
}
