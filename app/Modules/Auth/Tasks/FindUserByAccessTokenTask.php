<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tasks;

use App\Modules\Auth\Repositories\AccessTokenRepository;
use App\Modules\User\Models\User;
use App\Tasks\BaseTask;
use Illuminate\Support\Facades\Config;

final class FindUserByAccessTokenTask extends BaseTask
{
    public function __construct(
        private readonly AccessTokenRepository $repository,
    ) {
    }

    public function run(string $plainTextToken): ?User
    {
        if ($plainTextToken === '') {
            return null;
        }

        $expiration = Config::get('sanctum.expiration');

        return $this->repository->findUserByToken($plainTextToken, \is_int($expiration) ? $expiration : null);
    }
}
