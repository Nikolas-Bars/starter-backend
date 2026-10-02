<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Repositories\ChatMemberRepository;
use App\Tasks\BaseTask;

final class ListChatMemberIdsTask extends BaseTask
{
    public function __construct(
        private readonly ChatMemberRepository $repository,
    ) {
    }

    /**
     * @return list<int>
     */
    public function run(int $chatId): array
    {
        return $this->repository->userIds($chatId);
    }
}
