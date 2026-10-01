<?php

declare(strict_types=1);

namespace App\Modules\User\Tasks;

use App\Modules\User\DTO\UserStoreDTO;
use App\Modules\User\Models\User;
use App\Modules\User\Repositories\UserRepository;
use App\Tasks\BaseTask;

final class CreateUserTask extends BaseTask
{
    public function __construct(
        private readonly UserRepository $repository,
    ) {
    }

    public function run(UserStoreDTO $dto): User
    {
        return $this->repository->store($dto);
    }
}
