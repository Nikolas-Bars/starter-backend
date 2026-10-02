<?php

declare(strict_types=1);

namespace App\Modules\Call\Actions;

use App\Actions\BaseAction;
use App\Modules\Call\Tasks\IssueWebSocketTicketTask;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Config;

final class IssueWebSocketTicketAction extends BaseAction
{
    public function __construct(
        private readonly IssueWebSocketTicketTask $issueWebSocketTicketTask,
    ) {
    }

    /**
     * @return array{ticket: string, expires_in: int}
     */
    public function run(User $user): array
    {
        $ttl = Config::integer('calls.websocket.ticket_ttl');

        return [
            'ticket'     => $this->issueWebSocketTicketTask->run($user->id, $ttl),
            'expires_in' => $ttl,
        ];
    }
}
