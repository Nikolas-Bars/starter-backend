<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\Tasks\DeleteChatAttachmentsTask;
use App\Modules\Chat\Tasks\ListPrunableChatAttachmentsTask;
use Illuminate\Support\Carbon;

final class PruneChatAttachmentsAction extends BaseAction
{
    public function __construct(
        private readonly ListPrunableChatAttachmentsTask $listPrunableChatAttachmentsTask,
        private readonly DeleteChatAttachmentsTask       $deleteChatAttachmentsTask,
    ) {
    }

    /**
     * Удаляет файлы, которые так и не отправили за сутки, а с датой — ещё и все отправленные раньше неё.
     * Сообщения остаются: у них просто пропадают вложения.
     *
     * @return array{count: int, bytes: int} Сколько удалено
     */
    public function run(?Carbon $sentBefore = null): array
    {
        $attachments = $this->listPrunableChatAttachmentsTask->run($sentBefore);
        $bytes       = 0;

        foreach ($attachments as $attachment) {
            $bytes += $attachment->size;
        }

        $this->deleteChatAttachmentsTask->run($attachments);

        return ['count' => $attachments->count(), 'bytes' => $bytes];
    }
}
