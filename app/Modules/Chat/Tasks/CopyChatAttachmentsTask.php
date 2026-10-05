<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Enums\ChatAttachmentStatusEnum;
use App\Modules\Chat\Models\ChatAttachment;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Repositories\ChatAttachmentRepository;
use App\Tasks\BaseTask;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class CopyChatAttachmentsTask extends BaseTask
{
    public function __construct(
        private readonly ChatAttachmentRepository $repository,
    ) {
    }

    /**
     * Копирует готовые файлы в новое сообщение — у копии свой файл на диске, поэтому удаление
     * оригинала её не задевает. Вызывать внутри транзакции; $copied — пути новых файлов, чтобы убрать их при откате.
     *
     * @param Collection<int, ChatAttachment> $attachments
     * @param list<string>                    $copied
     *
     * @return Collection<int, ChatAttachment>
     */
    public function run(Collection $attachments, ChatMessage $message, array &$copied): Collection
    {
        $result = new Collection();

        foreach ($attachments as $attachment) {
            if ($attachment->status !== ChatAttachmentStatusEnum::Ready) {
                continue;
            }

            $path  = $this->copy($attachment->path, $copied);
            $thumb = $attachment->thumb_path === null ? null : $this->copy($attachment->thumb_path, $copied);

            /** @var ChatAttachment $copy */
            $copy = $this->repository->create([
                'user_id'       => $message->user_id,
                'message_id'    => $message->id,
                'kind'          => $attachment->kind,
                'status'        => $attachment->status,
                'original_name' => $attachment->original_name,
                'mime'          => $attachment->mime,
                'size'          => $attachment->size,
                'path'          => $path,
                'thumb_path'    => $thumb,
                'width'         => $attachment->width,
                'height'        => $attachment->height,
                'duration_ms'   => $attachment->duration_ms,
                'waveform'      => $attachment->waveform,
            ]);
            $result->push($copy);
        }

        return $result;
    }

    /**
     * @param list<string> $copied
     */
    private function copy(string $source, array &$copied): string
    {
        $extension = \pathinfo($source, \PATHINFO_EXTENSION);
        $target    = \dirname($source) . '/' . Str::lower(Str::random(40)) . ($extension === '' ? '' : '.' . $extension);

        if (!Storage::disk(Config::string('attachments.disk'))->copy($source, $target)) {
            throw new RuntimeException('Не удалось скопировать файл ' . $source);
        }

        $copied[] = $target;

        return $target;
    }
}
