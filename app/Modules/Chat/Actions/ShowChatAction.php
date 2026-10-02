<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\Exceptions\ChatNotFoundException;
use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Tasks\FindChatForMemberTask;
use App\Modules\User\Models\User;

final class ShowChatAction extends BaseAction
{
    public function __construct(
        private readonly FindChatForMemberTask $findChatForMemberTask,
    ) {
    }

    /**
     * @throws ChatNotFoundException
     */
    public function run(User $user, int $chatId): Chat
    {
        return $this->findChatForMemberTask->run($chatId, $user->id) ?? throw new ChatNotFoundException();
    }
}
