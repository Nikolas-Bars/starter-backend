<?php

declare(strict_types=1);

namespace App\Modules\Push\Tasks;

use App\Modules\Push\Models\PushDevice;
use App\Modules\Push\Repositories\PushDeviceRepository;
use App\Tasks\BaseTask;

final class RegisterPushDeviceTask extends BaseTask
{
    public function __construct(
        private readonly PushDeviceRepository $repository,
    ) {
    }

    public function run(int $userId, ?int $accessTokenId, string $token): PushDevice
    {
        return $this->repository->upsert($userId, $accessTokenId, $token);
    }
}
