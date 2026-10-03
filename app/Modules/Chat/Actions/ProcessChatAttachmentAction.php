<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\Enums\ChatAttachmentStatusEnum;
use App\Modules\Chat\Http\Resources\ChatAttachmentResource;
use App\Modules\Chat\Tasks\FindChatAttachmentRecipientTask;
use App\Modules\Chat\Tasks\FindChatAttachmentTask;
use App\Modules\Chat\Tasks\ListChatMemberIdsTask;
use App\Modules\Chat\Tasks\NotifyChatStorageUsageTask;
use App\Modules\Chat\Tasks\ProcessChatAttachmentMediaTask;
use App\Modules\Chat\Tasks\PublishChatEventTask;
use App\Modules\Chat\Tasks\UpdateChatAttachmentTask;

final class ProcessChatAttachmentAction extends BaseAction
{
    public const string EVENT = 'chat.attachment';

    public function __construct(
        private readonly FindChatAttachmentTask          $findChatAttachmentTask,
        private readonly ProcessChatAttachmentMediaTask  $processChatAttachmentMediaTask,
        private readonly UpdateChatAttachmentTask        $updateChatAttachmentTask,
        private readonly FindChatAttachmentRecipientTask $findChatAttachmentRecipientTask,
        private readonly ListChatMemberIdsTask           $listChatMemberIdsTask,
        private readonly PublishChatEventTask            $publishChatEventTask,
        private readonly NotifyChatStorageUsageTask      $notifyChatStorageUsageTask,
    ) {
    }

    /**
     * Сжимает файл и, если сообщение с ним уже отправлено, рассылает участникам чата
     * готовое вложение (событие chat.attachment).
     */
    public function run(int $attachmentId): void
    {
        $attachment = $this->findChatAttachmentTask->run($attachmentId);

        if ($attachment === null || $attachment->status !== ChatAttachmentStatusEnum::Processing) {
            return;
        }

        $attachment = $this->updateChatAttachmentTask->run($attachment, $this->processChatAttachmentMediaTask->run($attachment));

        // Отправка блокирует строку вложения: после записи статуса message_id в базе уже окончательный
        $recipient = $this->findChatAttachmentRecipientTask->run($attachment->id);

        if ($recipient !== null) {
            /** @var array<string, mixed> $payload */
            $payload = ChatAttachmentResource::make($attachment)->resolve();

            $this->publishChatEventTask->run($this->listChatMemberIdsTask->run($recipient['chat_id']), self::EVENT, [
                'chat_id'    => $recipient['chat_id'],
                'message_id' => $recipient['message_id'],
                'attachment' => $payload,
            ]);
        }

        $this->notifyChatStorageUsageTask->run();
    }
}
