<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Repositories\ChatMessageRepository;
use App\Modules\Chat\Repositories\ChatRepository;
use App\Tasks\BaseTask;

final class DeleteChatMessageTask extends BaseTask
{
    public function __construct(
        private readonly ChatMessageRepository $messageRepository,
        private readonly ChatRepository        $chatRepository,
    ) {
    }

    /**
     * Удаляет сообщение вместе с реакциями и записями о вложениях (файлы на диске — забота вызывающего).
     * Если оно было последним в чате, последним становится предыдущее.
     *
     * @return ChatMessage|null Новое последнее сообщение чата
     */
    public function run(Chat $chat, ChatMessage $message): ?ChatMessage
    {
        $this->messageRepository->delete($message);

        if ($chat->last_message_id !== $message->id) {
            return null;
        }

        $last = $this->messageRepository->latestInChat($chat->id);
        $this->chatRepository->updateLastMessage($chat, $last?->id);

        return $last;
    }
}
