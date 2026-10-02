<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\DTO\ChatReadStateDTO;
use App\Modules\Chat\Exceptions\ChatNotFoundException;
use App\Modules\Chat\Tasks\CountUnreadMessagesTask;
use App\Modules\Chat\Tasks\FindChatMemberTask;
use App\Modules\Chat\Tasks\FindChatTask;
use App\Modules\Chat\Tasks\ListChatMemberIdsTask;
use App\Modules\Chat\Tasks\PublishChatEventTask;
use App\Modules\Chat\Tasks\UpdateLastReadMessageTask;
use App\Modules\User\Models\User;

final class MarkChatReadAction extends BaseAction
{
    public const EVENT = 'chat.read';

    public function __construct(
        private readonly FindChatMemberTask        $findChatMemberTask,
        private readonly FindChatTask              $findChatTask,
        private readonly UpdateLastReadMessageTask $updateLastReadMessageTask,
        private readonly CountUnreadMessagesTask   $countUnreadMessagesTask,
        private readonly ListChatMemberIdsTask     $listChatMemberIdsTask,
        private readonly PublishChatEventTask      $publishChatEventTask,
    ) {
    }

    /**
     * Отмечает прочитанным всё до $messageId включительно. Если отметка сдвинулась,
     * участники получают chat.read: собеседник видит «прочитано», другие вкладки — новый счётчик.
     *
     * @throws ChatNotFoundException
     */
    public function run(User $user, int $chatId, int $messageId): ChatReadStateDTO
    {
        $member = $this->findChatMemberTask->run($chatId, $user->id);
        $chat   = $member === null ? null : $this->findChatTask->run($chatId);

        if ($member === null || $chat === null) {
            throw new ChatNotFoundException();
        }

        // Дальше последнего сообщения отметку не двигаем: иначе новые сообщения сразу стали бы прочитанными
        $moved = $this->updateLastReadMessageTask->run($member, \min($messageId, $chat->last_message_id ?? 0));

        $state = new ChatReadStateDTO(
            chat_id: $chatId,
            user_id: $user->id,
            last_read_message_id: $member->last_read_message_id,
            unread_count: $this->countUnreadMessagesTask->run($member),
        );

        if ($moved) {
            $this->publishChatEventTask->run($this->listChatMemberIdsTask->run($chatId), self::EVENT, [
                'chat_id'              => $state->chat_id,
                'user_id'              => $state->user_id,
                'last_read_message_id' => $state->last_read_message_id,
                'unread_count'         => $state->unread_count,
            ]);
        }

        return $state;
    }
}
