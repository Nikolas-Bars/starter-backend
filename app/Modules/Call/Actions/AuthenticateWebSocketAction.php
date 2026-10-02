<?php

declare(strict_types=1);

namespace App\Modules\Call\Actions;

use App\Actions\BaseAction;
use App\Modules\Call\Tasks\ConsumeWebSocketTicketTask;
use App\Modules\User\Models\User;
use App\Modules\User\Tasks\FindUserByIdTask;

final class AuthenticateWebSocketAction extends BaseAction
{
    public function __construct(
        private readonly ConsumeWebSocketTicketTask $consumeWebSocketTicketTask,
        private readonly FindUserByIdTask           $findUserByIdTask,
    ) {
    }

    /**
     * Браузерный WebSocket не умеет слать заголовок Authorization, а токен в адресе осел бы
     * в логах прокси. Поэтому клиент меняет токен на одноразовый короткоживущий билет
     * (POST /api/calls/ws-ticket) и передаёт его параметром строки запроса.
     */
    public function run(string $ticket): ?User
    {
        $userId = $this->consumeWebSocketTicketTask->run($ticket);

        return $userId === null ? null : $this->findUserByIdTask->run($userId);
    }
}
