<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\Enums\ChatMessageTypeEnum;
use App\Modules\Chat\Exceptions\ChatMessageAnsweredException;
use App\Modules\Chat\Exceptions\ChatMessageNotEditableException;
use App\Modules\Chat\Exceptions\ChatMessageNotFoundException;
use App\Modules\Chat\Exceptions\ChatNotFoundException;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Tasks\EditChatMessageTask;
use App\Modules\Chat\Tasks\FindChatMemberTask;
use App\Modules\Chat\Tasks\FindChatMessageTask;
use App\Modules\Chat\Tasks\IsChatMessageAnsweredTask;
use App\Modules\Chat\Tasks\NotifyChatMessageTask;
use App\Modules\User\Models\User;

final class EditChatMessageAction extends BaseAction
{
    public const EVENT = 'chat.message_updated';

    public function __construct(
        private readonly FindChatMemberTask        $findChatMemberTask,
        private readonly FindChatMessageTask       $findChatMessageTask,
        private readonly IsChatMessageAnsweredTask $isChatMessageAnsweredTask,
        private readonly EditChatMessageTask       $editChatMessageTask,
        private readonly NotifyChatMessageTask     $notifyChatMessageTask,
    ) {
    }

    /**
     * Меняет текст своего сообщения, пока на него не ответили. Участники получают chat.message_updated
     * с сообщением целиком: собеседник с другим языком — вместе с переводом нового текста (NotifyChatMessageTask),
     * автор — без переводов, перевод придёт ему событием chat.message_translated.
     * Тот же текст — без изменений и без события.
     *
     * @throws ChatNotFoundException
     * @throws ChatMessageNotFoundException
     * @throws ChatMessageNotEditableException
     * @throws ChatMessageAnsweredException
     */
    public function run(User $user, int $chatId, int $messageId, string $body): ChatMessage
    {
        if ($this->findChatMemberTask->run($chatId, $user->id) === null) {
            throw new ChatNotFoundException();
        }

        $message = $this->findChatMessageTask->run($chatId, $messageId) ?? throw new ChatMessageNotFoundException();

        if (
            $message->user_id !== $user->id
            || $message->type !== ChatMessageTypeEnum::Text
            || $message->forwarded_from_name !== null
        ) {
            throw new ChatMessageNotEditableException();
        }

        if ($this->isChatMessageAnsweredTask->run($message)) {
            throw new ChatMessageAnsweredException();
        }

        $message = $this->editChatMessageTask->run($message, $body);

        if ($message->wasChanged('body')) {
            $this->notifyChatMessageTask->run($message, self::EVENT);
        }

        return $message;
    }
}
