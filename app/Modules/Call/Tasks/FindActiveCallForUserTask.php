<?php

declare(strict_types=1);

namespace App\Modules\Call\Tasks;

use App\Modules\Call\Models\Call;
use App\Modules\Call\Repositories\CallRepository;
use App\Tasks\BaseTask;

final class FindActiveCallForUserTask extends BaseTask
{
    public function __construct(
        private readonly CallRepository $repository,
    ) {
    }

    public function run(int $userId): ?Call
    {
        return $this->repository->findOngoingForUser($userId);
    }
}
