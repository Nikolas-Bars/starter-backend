<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Enums\ChatAttachmentKindEnum;
use App\Tasks\BaseTask;
use Illuminate\Http\UploadedFile;

final class ResolveChatAttachmentKindTask extends BaseTask
{
    /**
     * Фото, которые умеем сжимать. SVG и прочее — обычными файлами: в SVG бывает скрипт
     */
    private const array IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic', 'image/heif'];

    /**
     * Голосовое из браузера приходит в webm (по содержимому похоже на видео), из приложения — в m4a
     */
    private const array VOICE_MIMES = ['video/webm', 'video/mp4', 'video/3gpp', 'application/ogg'];

    /**
     * Тип по содержимому файла, а не по имени и не по заголовку от клиента.
     *
     * @return array{ChatAttachmentKindEnum, string}
     */
    public function run(UploadedFile $file, bool $voice, bool $asFile): array
    {
        $mime = $file->getMimeType() ?? 'application/octet-stream';

        if ($asFile) {
            return [ChatAttachmentKindEnum::File, $mime];
        }

        if ($voice && (\str_starts_with($mime, 'audio/') || \in_array($mime, self::VOICE_MIMES, true))) {
            return [ChatAttachmentKindEnum::Voice, $mime];
        }

        if (\in_array($mime, self::IMAGE_MIMES, true)) {
            return [ChatAttachmentKindEnum::Image, $mime];
        }

        if (\str_starts_with($mime, 'video/')) {
            return [ChatAttachmentKindEnum::Video, $mime];
        }

        return [ChatAttachmentKindEnum::File, $mime];
    }
}
