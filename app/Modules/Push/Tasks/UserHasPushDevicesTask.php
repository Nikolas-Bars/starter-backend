<?php

declare(strict_types=1);

namespace App\Modules\Push\Tasks;

use App\Modules\Push\Repositories\PushDeviceRepository;
use App\Services\FcmClient;
use App\Tasks\BaseTask;

final class UserHasPushDevicesTask extends BaseTask
{
    public function __construct(
        private readonly PushDeviceRepository $repository,
        private readonly FcmClient            $fcmClient,
    ) {
    }

    /**
     * Можно ли достучаться до пользователя push: без ключа Firebase отправить их нечем
     */
    public function run(int $userId): bool
    {
        return $this->fcmClient->enabled() && $this->repository->existsForUser($userId);
    }
}
