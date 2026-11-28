<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Tasks;

use App\Modules\Vpn\Models\VpnProfile;
use App\Modules\Vpn\Repositories\VpnProfileRepository;
use App\Tasks\BaseTask;
use Illuminate\Database\Eloquent\Collection;

final class ListVpnProfilesTask extends BaseTask
{
    public function __construct(
        private readonly VpnProfileRepository $repository,
    ) {
    }

    /**
     * @return Collection<int, VpnProfile>
     */
    public function run(bool $onlyEnabled): Collection
    {
        return $this->repository->listOrdered($onlyEnabled);
    }
}
