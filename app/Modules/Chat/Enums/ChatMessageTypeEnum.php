<?php

declare(strict_types=1);

namespace App\Modules\Chat\Enums;

enum ChatMessageTypeEnum: string
{
    /**
     * Текст от пользователя
     */
    case Text = 'text';

    /**
     * Служебное: звонок между участниками чата. Автор — тот, кто звонил; текста нет
     */
    case Call = 'call';
}
