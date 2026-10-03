<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Enums\ChatAttachmentKindEnum;
use App\Modules\Chat\Enums\ChatAttachmentStatusEnum;
use App\Modules\Chat\Models\ChatAttachment;
use App\Services\MediaProcessor;
use App\Tasks\BaseTask;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ProcessChatAttachmentMediaTask extends BaseTask
{
    public function __construct(
        private readonly MediaProcessor $mediaProcessor,
    ) {
    }

    /**
     * Сжимает загруженный оригинал и удаляет его: хранится только результат без метаданных.
     * Битый файл не роняет очередь — вложение помечается испорченным.
     *
     * @return array<string, mixed> Что записать во вложение
     */
    public function run(ChatAttachment $attachment): array
    {
        $kind = $attachment->kind;

        if (!$kind->needsProcessing()) {
            return ['status' => ChatAttachmentStatusEnum::Ready];
        }

        $disk   = Storage::disk(Config::string('attachments.disk'));
        $source = $attachment->path;
        $base   = \preg_replace('/\.src$/', '', $source) ?? $source;
        $thumb  = $kind === ChatAttachmentKindEnum::Voice ? null : $base . '-thumb.jpg';
        $target = $base . match ($kind) {
            ChatAttachmentKindEnum::Image => match ($attachment->mime) {
                'image/png' => '.png',
                'image/gif' => '.gif',
                default     => '.jpg',
            },
            ChatAttachmentKindEnum::Video => '.mp4',
            default                       => '.m4a',
        };

        try {
            $result = match ($kind) {
                ChatAttachmentKindEnum::Image => $this->mediaProcessor->image($disk->path($source), $attachment->mime, $disk->path($target), $disk->path((string)$thumb)),
                ChatAttachmentKindEnum::Video => ['mime' => 'video/mp4', ...$this->mediaProcessor->video($disk->path($source), $disk->path($target), $disk->path((string)$thumb))],
                default                       => ['mime' => 'audio/mp4', ...$this->mediaProcessor->voice($disk->path($source), $disk->path($target))],
            };
        } catch (Throwable $exception) {
            Log::warning('Не удалось обработать вложение', ['attachment_id' => $attachment->id, 'error' => $exception->getMessage()]);
            $disk->delete($thumb === null ? [$source, $target] : [$source, $target, $thumb]);

            return ['status' => ChatAttachmentStatusEnum::Failed, 'size' => 0];
        }

        $disk->delete($source);

        return [
            ...$result,
            'status'     => ChatAttachmentStatusEnum::Ready,
            'path'       => $target,
            'thumb_path' => $thumb,
            'size'       => $disk->size($target) + ($thumb === null ? 0 : $disk->size($thumb)),
        ];
    }
}
