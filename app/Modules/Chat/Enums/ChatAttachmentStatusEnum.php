<?php

declare(strict_types=1);

namespace App\Modules\Chat\Enums;

enum ChatAttachmentStatusEnum: string
{
    /**
     * Загружен, очередь media ещё сжимает его
     */
    case Processing = 'processing';

    case Ready = 'ready';

    /**
     * Обработать не удалось (битый файл): показывать нечего, место не занимает
     */
    case Failed = 'failed';
}
