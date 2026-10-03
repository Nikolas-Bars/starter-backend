<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Exceptions\ChatAttachmentNotFoundException;
use App\Modules\Chat\Models\ChatAttachment;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Repositories\ChatAttachmentRepository;
use App\Tasks\BaseTask;
use Illuminate\Database\Eloquent\Collection;

final class AttachChatAttachmentsTask extends BaseTask
{
    public function __construct(
        private readonly ChatAttachmentRepository $repository,
    ) {
    }

    /**
     * Привязывает файлы к сообщению. Вызывать в транзакции: если хоть один файл чужой,
     * уже отправлен или не обработался, сообщение не должно сохраниться.
     *
     * @param list<int> $attachmentIds
     *
     *
     * @throws ChatAttachmentNotFoundException
     * @return Collection<int, ChatAttachment>
     */
    public function run(ChatMessage $message, int $userId, array $attachmentIds): Collection
    {
        $attachments = $this->repository->findSendable($userId, $attachmentIds);

        if ($attachments->count() !== \count($attachmentIds)) {
            throw new ChatAttachmentNotFoundException();
        }

        $this->repository->attachToMessage($attachmentIds, $message->id);

        foreach ($attachments as $attachment) {
            $attachment->message_id = $message->id;
            $attachment->syncOriginalAttribute('message_id');
        }

        return $attachments;
    }
}
