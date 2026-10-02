<?php

declare(strict_types=1);

namespace App\Modules\User\Tasks;

use App\Modules\User\DTO\ListUsersDTO;
use App\Modules\User\Models\User;
use App\Modules\User\Repositories\UserRepository;
use App\Tasks\BaseTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListUsersTask extends BaseTask
{
    public function __construct(
        private readonly UserRepository $repository,
    ) {
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function run(int $exceptUserId, ListUsersDTO $dto): LengthAwarePaginator
    {
        return $this->repository->paginateExcept($exceptUserId, $dto->search, $dto->per_page);
    }
}
