<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Tasks;

use App\Modules\Vpn\Repositories\VpnGrantRepository;
use App\Tasks\BaseTask;

final class UserHasVpnAccessTask extends BaseTask
{
    public function __construct(
        private readonly VpnGrantRepository $repository,
    ) {
    }

    public function run(int $userId): bool
    {
        return $this->repository->existsForUser($userId);
    }
}
