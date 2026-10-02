<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Repositories\ChatRepository;
use App\Tasks\BaseTask;

final class FindChatForMemberTask extends BaseTask
{
    public function __construct(
        private readonly ChatRepository $repository,
    ) {
    }

    /**
     * @return Chat|null null — чата нет или пользователь в нём не участвует
     */
    public function run(int $chatId, int $userId): ?Chat
    {
        return $this->repository->findForMember($chatId, $userId);
    }
}
