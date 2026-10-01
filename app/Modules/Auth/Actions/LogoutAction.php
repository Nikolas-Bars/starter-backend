<?php

declare(strict_types=1);

namespace App\Modules\Auth\Actions;

use App\Actions\BaseAction;
use App\Modules\Auth\Tasks\RevokeAccessTokenTask;
use App\Modules\User\Models\User;

final class LogoutAction extends BaseAction
{
    public function __construct(
        private readonly RevokeAccessTokenTask $revokeAccessTokenTask,
    ) {
    }

    /**
     * Отзывает только токен текущего запроса: остальные устройства остаются в системе.
     */
    public function run(User $user): void
    {
        $this->revokeAccessTokenTask->run($user->currentAccessToken());
    }
}
