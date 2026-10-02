<?php

declare(strict_types=1);

namespace App\Modules\Call\Actions;

use App\Actions\BaseAction;
use App\Modules\Auth\Tasks\FindUserByAccessTokenTask;
use App\Modules\User\Models\User;

final class AuthenticateWebSocketAction extends BaseAction
{
    public function __construct(
        private readonly FindUserByAccessTokenTask $findUserByAccessTokenTask,
    ) {
    }

    /**
     * Браузерный WebSocket не умеет слать заголовок Authorization, поэтому токен
     * приходит параметром строки запроса при подключении.
     */
    public function run(string $token): ?User
    {
        return $this->findUserByAccessTokenTask->run($token);
    }
}
