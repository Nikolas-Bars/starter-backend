<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\Models\ChatFolder;
use App\Modules\Chat\Tasks\ListChatFoldersTask;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class ListChatFoldersAction extends BaseAction
{
    public function __construct(
        private readonly ListChatFoldersTask $listChatFoldersTask,
    ) {
    }

    /**
     * @return Collection<int, ChatFolder>
     */
    public function run(User $user): Collection
    {
        return $this->listChatFoldersTask->run($user->id);
    }
}
