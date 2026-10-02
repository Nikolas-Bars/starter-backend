<?php

declare(strict_types=1);

namespace App\Modules\Push\Actions;

use App\Actions\BaseAction;
use App\Modules\Push\Models\PushDevice;
use App\Modules\Push\Tasks\RegisterPushDeviceTask;
use App\Modules\User\Models\User;

final class RegisterPushDeviceAction extends BaseAction
{
    public function __construct(
        private readonly RegisterPushDeviceTask $registerPushDeviceTask,
    ) {
    }

    /**
     * Устройство привязывается к токену входа, с которым пришёл запрос: выход из аккаунта его удалит
     */
    public function run(User $user, string $token): PushDevice
    {
        // В тестах вместо токена из БД — заглушка Sanctum без id
        $accessTokenId = $user->currentAccessToken()->getKey();

        return $this->registerPushDeviceTask->run($user->id, \is_int($accessTokenId) ? $accessTokenId : null, $token);
    }
}
