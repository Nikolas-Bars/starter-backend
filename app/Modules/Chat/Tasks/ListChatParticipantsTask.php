<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatMember;
use App\Modules\Chat\Repositories\ChatMemberRepository;
use App\Tasks\BaseTask;

final class ListChatParticipantsTask extends BaseTask
{
    public function __construct(
        private readonly ChatMemberRepository $repository,
    ) {
    }

    /**
     * Участники вместе с пользователями: имена и языки интерфейса
     *
     * @return list<ChatMember>
     */
    public function run(int $chatId): array
    {
        return $this->repository->withUsers($chatId);
    }
}
