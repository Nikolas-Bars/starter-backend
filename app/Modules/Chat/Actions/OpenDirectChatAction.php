<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\Exceptions\ChatNotFoundException;
use App\Modules\Chat\Exceptions\InvalidChatPeerException;
use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Tasks\CreateDirectChatTask;
use App\Modules\Chat\Tasks\FindChatForMemberTask;
use App\Modules\Chat\Tasks\FindDirectChatTask;
use App\Modules\User\Models\User;
use App\Modules\User\Tasks\FindUserByIdTask;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class OpenDirectChatAction extends BaseAction
{
    public function __construct(
        private readonly FindUserByIdTask      $findUserByIdTask,
        private readonly FindDirectChatTask    $findDirectChatTask,
        private readonly CreateDirectChatTask  $createDirectChatTask,
        private readonly FindChatForMemberTask $findChatForMemberTask,
    ) {
    }

    /**
     * Личный чат с пользователем: существующий или новый. Гости по ссылке для звонка
     * в переписке не участвуют.
     *
     * @throws InvalidChatPeerException
     * @throws ChatNotFoundException
     */
    public function run(User $user, int $peerId): Chat
    {
        $peer = $peerId === $user->id ? null : $this->findUserByIdTask->run($peerId);

        if ($peer === null || $peer->isGuest()) {
            throw new InvalidChatPeerException();
        }

        $chat = $this->findDirectChatTask->run($user->id, $peer->id) ?? $this->create($user->id, $peer->id);

        return $this->findChatForMemberTask->run($chat->id, $user->id) ?? throw new ChatNotFoundException();
    }

    private function create(int $userId, int $peerId): Chat
    {
        try {
            return DB::transaction(fn(): Chat => $this->createDirectChatTask->run($userId, $peerId));
        } catch (UniqueConstraintViolationException $exception) {
            // Собеседник открыл этот же чат одновременно с нами
            return $this->findDirectChatTask->run($userId, $peerId) ?? throw $exception;
        }
    }
}
