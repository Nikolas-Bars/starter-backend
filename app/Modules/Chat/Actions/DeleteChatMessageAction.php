<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\Enums\ChatMessageTypeEnum;
use App\Modules\Chat\Exceptions\ChatMessageForbiddenException;
use App\Modules\Chat\Exceptions\ChatMessageNotFoundException;
use App\Modules\Chat\Exceptions\ChatNotFoundException;
use App\Modules\Chat\Http\Resources\ChatMessageResource;
use App\Modules\Chat\Tasks\DeleteChatMessageTask;
use App\Modules\Chat\Tasks\FindChatMemberTask;
use App\Modules\Chat\Tasks\FindChatMessageTask;
use App\Modules\Chat\Tasks\FindChatTask;
use App\Modules\Chat\Tasks\ListChatMemberIdsTask;
use App\Modules\Chat\Tasks\PublishChatEventTask;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class DeleteChatMessageAction extends BaseAction
{
    public const EVENT = 'chat.message_deleted';

    public function __construct(
        private readonly FindChatMemberTask    $findChatMemberTask,
        private readonly FindChatTask          $findChatTask,
        private readonly FindChatMessageTask   $findChatMessageTask,
        private readonly DeleteChatMessageTask $deleteChatMessageTask,
        private readonly ListChatMemberIdsTask $listChatMemberIdsTask,
        private readonly PublishChatEventTask  $publishChatEventTask,
    ) {
    }

    /**
     * Удаляет своё сообщение у всех вместе с его файлами. Участники получают событие chat.message_deleted;
     * если удалено последнее сообщение, в событии — новое последнее (или null).
     *
     * @throws ChatNotFoundException
     * @throws ChatMessageNotFoundException
     * @throws ChatMessageForbiddenException
     */
    public function run(User $user, int $chatId, int $messageId): void
    {
        $chat = $this->findChatMemberTask->run($chatId, $user->id) === null ? null : $this->findChatTask->run($chatId);

        if ($chat === null) {
            throw new ChatNotFoundException();
        }

        $message = $this->findChatMessageTask->run($chatId, $messageId) ?? throw new ChatMessageNotFoundException();

        if ($message->user_id !== $user->id || $message->type !== ChatMessageTypeEnum::Text) {
            throw new ChatMessageForbiddenException();
        }

        $paths = [];

        foreach ($message->attachments as $attachment) {
            $paths[] = $attachment->path;

            if ($attachment->thumb_path !== null) {
                $paths[] = $attachment->thumb_path;
            }
        }

        $wasLast = $chat->last_message_id === $message->id;
        $last    = DB::transaction(fn() => $this->deleteChatMessageTask->run($chat, $message));

        // Файлы — после фиксации: откат транзакции не вернул бы их с диска
        Storage::disk(Config::string('attachments.disk'))->delete($paths);

        $this->publishChatEventTask->run($this->listChatMemberIdsTask->run($chatId), self::EVENT, [
            'chat_id'      => $chatId,
            'message_id'   => $messageId,
            'user_id'      => $user->id,
            'last_changed' => $wasLast,
            'last_message' => $last === null ? null : ChatMessageResource::make($last)->resolve(),
        ]);
    }
}
