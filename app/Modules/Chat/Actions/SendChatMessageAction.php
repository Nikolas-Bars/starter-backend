<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\DTO\SendChatMessageDTO;
use App\Modules\Chat\Exceptions\ChatNotFoundException;
use App\Modules\Chat\Http\Resources\ChatMessageResource;
use App\Modules\Chat\Models\ChatMember;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Tasks\AppendChatMessageTask;
use App\Modules\Chat\Tasks\FindChatMemberTask;
use App\Modules\Chat\Tasks\FindChatMessageByClientIdTask;
use App\Modules\Chat\Tasks\FindChatTask;
use App\Modules\Chat\Tasks\ListChatMemberIdsTask;
use App\Modules\Chat\Tasks\PublishChatEventTask;
use App\Modules\Chat\Tasks\UpdateLastReadMessageTask;
use App\Modules\User\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class SendChatMessageAction extends BaseAction
{
    public const EVENT = 'chat.message';

    public function __construct(
        private readonly FindChatMemberTask            $findChatMemberTask,
        private readonly FindChatTask                  $findChatTask,
        private readonly FindChatMessageByClientIdTask $findChatMessageByClientIdTask,
        private readonly AppendChatMessageTask         $appendChatMessageTask,
        private readonly UpdateLastReadMessageTask     $updateLastReadMessageTask,
        private readonly ListChatMemberIdsTask         $listChatMemberIdsTask,
        private readonly PublishChatEventTask          $publishChatEventTask,
    ) {
    }

    /**
     * Сохраняет сообщение и рассылает его участникам чата (событие chat.message).
     * Повтор с тем же client_id возвращает уже сохранённое сообщение и ничего не рассылает.
     *
     * @throws ChatNotFoundException
     */
    public function run(User $user, int $chatId, SendChatMessageDTO $dto): ChatMessage
    {
        $member = $this->findChatMemberTask->run($chatId, $user->id);
        $chat   = $member === null ? null : $this->findChatTask->run($chatId);

        if ($member === null || $chat === null) {
            throw new ChatNotFoundException();
        }

        $existing = $this->findChatMessageByClientIdTask->run($user->id, $dto->client_id);

        if ($existing !== null) {
            return $existing;
        }

        try {
            $message = DB::transaction(function () use ($chat, $member, $user, $dto): ChatMessage {
                $message = $this->appendChatMessageTask->run($chat, $user->id, $dto->client_id, $dto->body);
                // Своё сообщение автор уже прочитал
                $this->updateLastReadMessageTask->run($member, $message->id);

                return $message;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // Тот же запрос пришёл повторно и успел сохраниться раньше
            return $this->findChatMessageByClientIdTask->run($user->id, $dto->client_id) ?? throw $exception;
        }

        $this->publish($member, $message);

        return $message;
    }

    private function publish(ChatMember $member, ChatMessage $message): void
    {
        /** @var array<string, mixed> $payload */
        $payload = ChatMessageResource::make($message)->resolve();

        $this->publishChatEventTask->run($this->listChatMemberIdsTask->run($member->chat_id), self::EVENT, ['message' => $payload]);
    }
}
