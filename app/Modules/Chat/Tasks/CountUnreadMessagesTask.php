<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatMember;
use App\Modules\Chat\Repositories\ChatMessageRepository;
use App\Tasks\BaseTask;

final class CountUnreadMessagesTask extends BaseTask
{
    public function __construct(
        private readonly ChatMessageRepository $repository,
    ) {
    }

    public function run(ChatMember $member): int
    {
        return $this->repository->countUnread($member->chat_id, $member->user_id, $member->last_read_message_id);
    }
}
