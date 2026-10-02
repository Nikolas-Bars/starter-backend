<?php

declare(strict_types=1);

namespace App\Modules\User\Tasks;

use App\Modules\User\DTO\UpdateProfileDTO;
use App\Modules\User\Models\User;
use App\Modules\User\Repositories\UserRepository;
use App\Tasks\BaseTask;

final class UpdateUserProfileTask extends BaseTask
{
    public function __construct(
        private readonly UserRepository $repository,
    ) {
    }

    public function run(User $user, UpdateProfileDTO $dto): User
    {
        return $this->repository->updateProfile($user, $dto->name, $dto->username);
    }
}
