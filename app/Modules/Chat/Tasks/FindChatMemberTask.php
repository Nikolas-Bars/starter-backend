<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatMember;
use App\Modules\Chat\Repositories\ChatMemberRepository;
use App\Tasks\BaseTask;

final class FindChatMemberTask extends BaseTask
{
    public function __construct(
        private readonly ChatMemberRepository $repository,
    ) {
    }

    public function run(int $chatId, int $userId): ?ChatMember
    {
        return $this->repository->find($chatId, $userId);
    }
}
