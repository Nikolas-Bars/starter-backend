<?php

declare(strict_types=1);

namespace App\Modules\Call\Tasks;

use App\Modules\Call\Models\Call;
use App\Modules\Call\Repositories\CallRepository;
use App\Tasks\BaseTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListUserCallsTask extends BaseTask
{
    public function __construct(
        private readonly CallRepository $repository,
    ) {
    }

    /**
     * @return LengthAwarePaginator<int, Call>
     */
    public function run(int $userId, int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateForUser($userId, $perPage);
    }
}
