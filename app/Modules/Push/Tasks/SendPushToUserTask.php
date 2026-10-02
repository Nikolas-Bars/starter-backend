<?php

declare(strict_types=1);

namespace App\Modules\Push\Tasks;

use App\Modules\Push\Repositories\PushDeviceRepository;
use App\Services\FcmClient;
use App\Tasks\BaseTask;

final class SendPushToUserTask extends BaseTask
{
    public function __construct(
        private readonly PushDeviceRepository $repository,
        private readonly FcmClient            $fcmClient,
    ) {
    }

    /**
     * Шлёт data-сообщение на все телефоны пользователя; устройства, которых больше нет, забывает.
     *
     * @param array<string, string> $data
     *
     * @return int На сколько устройств доставлено
     */
    public function run(int $userId, array $data, int $ttlSeconds): int
    {
        if (!$this->fcmClient->enabled()) {
            return 0;
        }

        $sent    = 0;
        $invalid = [];

        foreach ($this->repository->tokensOfUser($userId) as $token) {
            $result = $this->fcmClient->send($token, $data, $ttlSeconds);

            if ($result === FcmClient::SENT) {
                ++$sent;
            } elseif ($result === FcmClient::INVALID_TOKEN) {
                $invalid[] = $token;
            }
        }

        $this->repository->deleteTokens($invalid);

        return $sent;
    }
}
