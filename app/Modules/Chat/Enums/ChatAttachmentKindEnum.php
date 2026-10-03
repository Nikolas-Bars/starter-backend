<?php

declare(strict_types=1);

namespace App\Modules\Chat\Enums;

enum ChatAttachmentKindEnum: string
{
    /**
     * Фото: без метаданных (в том числе координат), уменьшенное, с превью
     */
    case Image = 'image';

    /**
     * Видео, сжатое до 720p, с кадром-превью
     */
    case Video = 'video';

    /**
     * Голосовое сообщение: m4a с полоской громкости
     */
    case Voice = 'voice';

    /**
     * Любой файл как есть; отдаётся только на скачивание
     */
    case File = 'file';

    public function needsProcessing(): bool
    {
        return $this !== self::File;
    }
}
