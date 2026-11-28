<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Tasks;

use App\Modules\Vpn\DTO\VpnProfileDTO;
use App\Modules\Vpn\Models\VpnProfile;
use App\Modules\Vpn\Repositories\VpnProfileRepository;
use App\Tasks\BaseTask;

final class CreateVpnProfileTask extends BaseTask
{
    public function __construct(
        private readonly VpnProfileRepository $repository,
    ) {
    }

    public function run(VpnProfileDTO $dto): VpnProfile
    {
        return $this->repository->store($dto);
    }
}
