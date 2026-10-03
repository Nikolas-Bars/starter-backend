<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatAttachment;
use App\Modules\Chat\Repositories\ChatAttachmentRepository;
use App\Tasks\BaseTask;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

final class DeleteChatAttachmentsTask extends BaseTask
{
    public function __construct(
        private readonly ChatAttachmentRepository $repository,
    ) {
    }

    /**
     * Удаляет файлы с диска и записи о них
     *
     * @param Collection<int, ChatAttachment> $attachments
     */
    public function run(Collection $attachments): void
    {
        if ($attachments->isEmpty()) {
            return;
        }

        $paths = [];

        foreach ($attachments as $attachment) {
            $paths[] = $attachment->path;

            if ($attachment->thumb_path !== null) {
                $paths[] = $attachment->thumb_path;
            }
        }

        // Файла может уже не быть (испорченное вложение): delete это переживает
        Storage::disk(Config::string('attachments.disk'))->delete($paths);
        $this->repository->deleteMany(\array_values($attachments->modelKeys()));
    }
}
