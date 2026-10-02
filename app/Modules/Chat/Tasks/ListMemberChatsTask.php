<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Repositories\ChatRepository;
use App\Tasks\BaseTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListMemberChatsTask extends BaseTask
{
    public function __construct(
        private readonly ChatRepository $repository,
    ) {
    }

    /**
     * @return LengthAwarePaginator<int, Chat>
     */
    public function run(int $userId, ?int $folderId, int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateForMember($userId, $folderId, $perPage);
    }
}
