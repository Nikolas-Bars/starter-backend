<?php

declare(strict_types=1);

namespace App\Modules\User\Actions;

use App\Actions\BaseAction;
use App\Modules\User\DTO\UpdateProfileDTO;
use App\Modules\User\Models\User;
use App\Modules\User\Tasks\UpdateUserProfileTask;

final class UpdateProfileAction extends BaseAction
{
    public function __construct(
        private readonly UpdateUserProfileTask $updateUserProfileTask,
    ) {
    }

    public function run(User $user, UpdateProfileDTO $dto): User
    {
        return $this->updateUserProfileTask->run($user, $dto);
    }
}
