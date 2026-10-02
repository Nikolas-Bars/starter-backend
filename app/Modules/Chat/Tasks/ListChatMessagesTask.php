<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Repositories\ChatMessageRepository;
use App\Tasks\BaseTask;
use Illuminate\Database\Eloquent\Collection;

final class ListChatMessagesTask extends BaseTask
{
    public function __construct(
        private readonly ChatMessageRepository $repository,
    ) {
    }

    /**
     * @return Collection<int, ChatMessage> от новых к старым
     */
    public function run(int $chatId, ?int $beforeId, int $limit): Collection
    {
        return $this->repository->latestBefore($chatId, $beforeId, $limit);
    }
}
