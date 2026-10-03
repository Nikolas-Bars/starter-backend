<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Repositories\ChatAttachmentRepository;
use App\Tasks\BaseTask;

final class FindChatAttachmentRecipientTask extends BaseTask
{
    public function __construct(
        private readonly ChatAttachmentRepository $repository,
    ) {
    }

    /**
     * В какое сообщение и чат отправлен файл; null — ещё не отправлен
     *
     * @return array{message_id: int, chat_id: int}|null
     */
    public function run(int $attachmentId): ?array
    {
        return $this->repository->findSentTo($attachmentId);
    }
}
