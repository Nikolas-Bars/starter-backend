<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\DTO\ForwardChatMessageDTO;
use App\Modules\Chat\Enums\ChatAttachmentStatusEnum;
use App\Modules\Chat\Enums\ChatMessageTypeEnum;
use App\Modules\Chat\Exceptions\ChatMessageNotForwardableException;
use App\Modules\Chat\Exceptions\ChatMessageNotFoundException;
use App\Modules\Chat\Exceptions\ChatNotFoundException;
use App\Modules\Chat\Exceptions\ChatStorageFullException;
use App\Modules\Chat\Http\Resources\ChatMessageResource;
use App\Modules\Chat\Models\ChatAttachment;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Tasks\AppendChatMessageTask;
use App\Modules\Chat\Tasks\CopyChatAttachmentsTask;
use App\Modules\Chat\Tasks\FindChatMemberTask;
use App\Modules\Chat\Tasks\FindChatMessageByClientIdTask;
use App\Modules\Chat\Tasks\FindChatMessageWithAttachmentsTask;
use App\Modules\Chat\Tasks\FindChatTask;
use App\Modules\Chat\Tasks\GetChatStorageUsageTask;
use App\Modules\Chat\Tasks\ListChatMemberIdsTask;
use App\Modules\Chat\Tasks\PublishChatEventTask;
use App\Modules\Chat\Tasks\QueueChatMessageTranslationTask;
use App\Modules\Chat\Tasks\UpdateLastReadMessageTask;
use App\Modules\User\Models\User;
use App\Modules\User\Tasks\FindUserByIdTask;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ForwardChatMessageAction extends BaseAction
{
    public function __construct(
        private readonly FindChatMemberTask                 $findChatMemberTask,
        private readonly FindChatTask                       $findChatTask,
        private readonly FindChatMessageWithAttachmentsTask $findChatMessageWithAttachmentsTask,
        private readonly FindChatMessageByClientIdTask      $findChatMessageByClientIdTask,
        private readonly FindUserByIdTask                   $findUserByIdTask,
        private readonly GetChatStorageUsageTask            $getChatStorageUsageTask,
        private readonly AppendChatMessageTask              $appendChatMessageTask,
        private readonly CopyChatAttachmentsTask            $copyChatAttachmentsTask,
        private readonly UpdateLastReadMessageTask          $updateLastReadMessageTask,
        private readonly ListChatMemberIdsTask              $listChatMemberIdsTask,
        private readonly PublishChatEventTask               $publishChatEventTask,
        private readonly QueueChatMessageTranslationTask    $queueChatMessageTranslationTask,
    ) {
    }

    /**
     * Пересылает сообщение из любого своего чата в чат $chatId: текст и готовые файлы копируются,
     * у копии — «Переслано от» с автором оригинала. Участники получают обычное событие chat.message.
     *
     * @throws ChatNotFoundException
     * @throws ChatMessageNotFoundException
     * @throws ChatMessageNotForwardableException
     * @throws ChatStorageFullException
     */
    public function run(User $user, int $chatId, ForwardChatMessageDTO $dto): ChatMessage
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

        $source = $this->findChatMessageWithAttachmentsTask->run($dto->message_id);

        if ($source === null || $this->findChatMemberTask->run($source->chat_id, $user->id) === null) {
            throw new ChatMessageNotFoundException();
        }

        $ready = $source->attachments->filter(static fn(ChatAttachment $attachment): bool => $attachment->status === ChatAttachmentStatusEnum::Ready);

        if ($source->type !== ChatMessageTypeEnum::Text || ($source->body === '' && $ready->isEmpty())) {
            throw new ChatMessageNotForwardableException();
        }

        if ($this->getChatStorageUsageTask->run() + \array_sum($ready->map(static fn(ChatAttachment $attachment): int => $attachment->size)->all()) > Config::integer('attachments.quota_bytes')) {
            throw new ChatStorageFullException();
        }

        $forwardedFrom = [
            'user_id' => $source->forwarded_from_name === null ? $source->user_id : $source->forwarded_from_user_id,
            'name'    => $source->forwarded_from_name ?? $this->findUserByIdTask->run($source->user_id)->name ?? '',
        ];

        /** @var list<string> $copied */
        $copied = [];

        try {
            $message = DB::transaction(function () use ($chat, $member, $user, $dto, $source, $forwardedFrom, &$copied): ChatMessage {
                $message = $this->appendChatMessageTask->run($chat, $user->id, $dto->client_id, $source->body, null, $forwardedFrom);
                $message->setRelation('attachments', $this->copyChatAttachmentsTask->run($source->attachments, $message, $copied));
                $this->updateLastReadMessageTask->run($member, $message->id);

                return $message;
            });
        } catch (UniqueConstraintViolationException $exception) {
            Storage::disk(Config::string('attachments.disk'))->delete($copied);

            return $this->findChatMessageByClientIdTask->run($user->id, $dto->client_id) ?? throw $exception;
        } catch (Throwable $exception) {
            Storage::disk(Config::string('attachments.disk'))->delete($copied);

            throw $exception;
        }

        /** @var array<string, mixed> $payload */
        $payload = ChatMessageResource::make($message)->resolve();
        $this->publishChatEventTask->run($this->listChatMemberIdsTask->run($chatId), SendChatMessageAction::EVENT, ['message' => $payload]);
        $this->queueChatMessageTranslationTask->run($message);

        return $message;
    }
}
