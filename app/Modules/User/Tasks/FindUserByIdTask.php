<?php

declare(strict_types=1);

namespace App\Modules\User\Tasks;

use App\Modules\User\Models\User;
use App\Modules\User\Repositories\UserRepository;
use App\Tasks\BaseTask;

final class FindUserByIdTask extends BaseTask
{
    public function __construct(
        private readonly UserRepository $repository,
    ) {
    }

    public function run(int $id): ?User
    {
        return $this->repository->findById($id);
    }
}
