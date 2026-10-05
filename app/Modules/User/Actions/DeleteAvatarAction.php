<?php

declare(strict_types=1);

namespace App\Modules\User\Actions;

use App\Actions\BaseAction;
use App\Modules\User\Models\User;
use App\Modules\User\Tasks\SetUserAvatarTask;

final class DeleteAvatarAction extends BaseAction
{
    public function __construct(
        private readonly SetUserAvatarTask $setUserAvatarTask,
    ) {
    }

    public function run(User $user): User
    {
        return $this->setUserAvatarTask->run($user, null);
    }
}
