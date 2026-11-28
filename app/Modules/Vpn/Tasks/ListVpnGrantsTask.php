<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Tasks;

use App\Modules\Vpn\Models\VpnGrant;
use App\Modules\Vpn\Repositories\VpnGrantRepository;
use App\Tasks\BaseTask;
use Illuminate\Database\Eloquent\Collection;

final class ListVpnGrantsTask extends BaseTask
{
    public function __construct(
        private readonly VpnGrantRepository $repository,
    ) {
    }

    /**
     * @return Collection<int, VpnGrant>
     */
    public function run(): Collection
    {
        return $this->repository->listWithUsers();
    }
}
