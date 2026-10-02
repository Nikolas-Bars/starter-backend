<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatMember;
use App\Modules\Chat\Repositories\ChatMemberRepository;
use App\Tasks\BaseTask;

final class UpdateLastReadMessageTask extends BaseTask
{
    public function __construct(
        private readonly ChatMemberRepository $repository,
    ) {
    }

    /**
     * Отметка прочитанного только сдвигается вперёд
     *
     * @return bool Сдвинулась ли отметка
     */
    public function run(ChatMember $member, int $messageId): bool
    {
        if ($messageId <= $member->last_read_message_id) {
            return false;
        }

        $this->repository->updateLastRead($member, $messageId);

        return true;
    }
}
