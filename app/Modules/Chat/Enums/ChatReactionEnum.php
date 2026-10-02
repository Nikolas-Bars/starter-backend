<?php

declare(strict_types=1);

namespace App\Modules\Chat\Enums;

/**
 * Реакции, которые можно поставить на сообщение. Тот же набор в фронтенде (src/stores/chat.ts)
 */
enum ChatReactionEnum: string
{
    case Like    = '👍';
    case Heart   = '❤️';
    case Laugh   = '😂';
    case Wow     = '😮';
    case Sad     = '😢';
    case Fire    = '🔥';
    case Pray    = '🙏';
    case Dislike = '👎';
}
