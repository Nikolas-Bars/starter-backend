<?php

declare(strict_types=1);

namespace App\Modules\User\Tasks;

use App\Modules\User\Models\User;
use App\Modules\User\Repositories\UserRepository;
use App\Tasks\BaseTask;

final class UpdateUserLocaleTask extends BaseTask
{
    public function __construct(
        private readonly UserRepository $repository,
    ) {
    }

    public function run(User $user, string $locale): User
    {
        return $this->repository->updateLocale($user, $locale);
    }
}
