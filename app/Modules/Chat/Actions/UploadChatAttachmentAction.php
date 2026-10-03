<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\DTO\UploadChatAttachmentDTO;
use App\Modules\Chat\Exceptions\ChatStorageFullException;
use App\Modules\Chat\Jobs\ProcessChatAttachmentJob;
use App\Modules\Chat\Models\ChatAttachment;
use App\Modules\Chat\Tasks\CreateChatAttachmentTask;
use App\Modules\Chat\Tasks\GetChatStorageUsageTask;
use App\Modules\Chat\Tasks\NotifyChatStorageUsageTask;
use App\Modules\Chat\Tasks\ResolveChatAttachmentKindTask;
use App\Modules\Chat\Tasks\StoreChatAttachmentFileTask;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Config;

final class UploadChatAttachmentAction extends BaseAction
{
    public const string QUEUE = 'media';

    public function __construct(
        private readonly ResolveChatAttachmentKindTask $resolveChatAttachmentKindTask,
        private readonly GetChatStorageUsageTask       $getChatStorageUsageTask,
        private readonly StoreChatAttachmentFileTask   $storeChatAttachmentFileTask,
        private readonly CreateChatAttachmentTask      $createChatAttachmentTask,
        private readonly NotifyChatStorageUsageTask    $notifyChatStorageUsageTask,
    ) {
    }

    /**
     * Сохраняет файл, пока ни к чему не привязанный: его id потом передают при отправке сообщения.
     * Фото, видео и голосовые сжимаются в очереди media; не отправленное за сутки удаляется.
     *
     * @throws ChatStorageFullException
     */
    public function run(User $user, UploadChatAttachmentDTO $dto): ChatAttachment
    {
        $size = (int)$dto->file->getSize();

        if ($this->getChatStorageUsageTask->run() + $size > Config::integer('attachments.quota_bytes')) {
            $this->notifyChatStorageUsageTask->run();

            throw new ChatStorageFullException();
        }

        [$kind, $mime] = $this->resolveChatAttachmentKindTask->run($dto->file, $dto->voice, $dto->as_file);
        $path          = $this->storeChatAttachmentFileTask->run($dto->file, $kind);
        $attachment    = $this->createChatAttachmentTask->run($user->id, $kind, $dto->original_name, $mime, $size, $path);

        if ($kind->needsProcessing()) {
            dispatch(new ProcessChatAttachmentJob($attachment->id))->onQueue(self::QUEUE);
        }

        $this->notifyChatStorageUsageTask->run();

        return $attachment;
    }
}
