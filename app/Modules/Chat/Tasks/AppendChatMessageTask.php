<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Call\Models\Call;
use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Repositories\ChatMessageRepository;
use App\Modules\Chat\Repositories\ChatRepository;
use App\Tasks\BaseTask;

final class AppendChatMessageTask extends BaseTask
{
    public function __construct(
        private readonly ChatMessageRepository $messageRepository,
        private readonly ChatRepository        $chatRepository,
    ) {
    }

    /**
     * Сохраняет сообщение и делает его последним в чате. Вызывать внутри транзакции.
     *
     * @param Call|null $call Звонок для служебного сообщения; null — обычный текст
     */
    public function run(Chat $chat, int $userId, string $clientId, string $body, ?Call $call = null): ChatMessage
    {
        $message = $this->messageRepository->store($chat->id, $userId, $clientId, $body, $call);

        $this->chatRepository->updateLastMessage($chat, $message->id);

        return $message;
    }
}
