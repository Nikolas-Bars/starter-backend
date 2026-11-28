<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Tasks;

use App\Modules\Vpn\Repositories\VpnGrantRepository;
use App\Tasks\BaseTask;

final class GrantVpnAccessTask extends BaseTask
{
    public function __construct(
        private readonly VpnGrantRepository $repository,
    ) {
    }

    /**
     * @return bool Выдан ли доступ только что (false — уже был)
     */
    public function run(int $userId): bool
    {
        return $this->repository->grant($userId);
    }
}
