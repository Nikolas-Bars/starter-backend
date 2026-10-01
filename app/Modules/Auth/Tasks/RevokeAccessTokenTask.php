<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tasks;

use App\Modules\Auth\Repositories\AccessTokenRepository;
use App\Tasks\BaseTask;
use Laravel\Sanctum\PersonalAccessToken;

final class RevokeAccessTokenTask extends BaseTask
{
    public function __construct(
        private readonly AccessTokenRepository $repository,
    ) {
    }

    public function run(PersonalAccessToken $token): void
    {
        $this->repository->revoke($token);
    }
}
