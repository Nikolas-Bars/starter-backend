<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Actions\EditChatMessageAction;
use App\Modules\Chat\Http\Resources\ChatMessageResource;
use App\Modules\Chat\Models\ChatMessage;
use App\Tasks\BaseTask;

final class PublishChatMessageEventTask extends BaseTask
{
    public function __construct(
        private readonly PublishChatEventTask $publishChatEventTask,
    ) {
    }

    /**
     * Сообщение целиком: chat.message — {message}, chat.message_updated — {chat_id, message}
     *
     * @param list<int> $userIds
     */
    public function run(array $userIds, string $event, ChatMessage $message): void
    {
        if ($userIds === []) {
            return;
        }

        /** @var array<string, mixed> $payload */
        $payload = ChatMessageResource::make($message)->resolve();
        $data    = $event === EditChatMessageAction::EVENT ? ['chat_id' => $message->chat_id, 'message' => $payload] : ['message' => $payload];

        $this->publishChatEventTask->run($userIds, $event, $data);
    }
}
