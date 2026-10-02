<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\Exceptions\ChatNotFoundException;
use App\Modules\Chat\Tasks\FindChatMemberTask;
use App\Modules\Chat\Tasks\ListChatMemberIdsTask;
use App\Modules\User\Models\User;

final class ListTypingRecipientsAction extends BaseAction
{
    public const EVENT = 'chat.typing';

    public function __construct(
        private readonly FindChatMemberTask    $findChatMemberTask,
        private readonly ListChatMemberIdsTask $listChatMemberIdsTask,
    ) {
    }

    /**
     * Кому сервер сигнализации перешлёт «пользователь печатает»: остальным участникам чата.
     * Само событие нигде не хранится.
     *
     *
     * @throws ChatNotFoundException
     * @return list<int>
     */
    public function run(User $user, int $chatId): array
    {
        if ($user->isGuest() || $this->findChatMemberTask->run($chatId, $user->id) === null) {
            throw new ChatNotFoundException();
        }

        return \array_values(\array_filter(
            $this->listChatMemberIdsTask->run($chatId),
            static fn(int $memberId): bool => $memberId !== $user->id,
        ));
    }
}
