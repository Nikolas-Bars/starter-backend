<?php

declare(strict_types=1);

namespace App\Modules\Chat\DTO;

use App\Modules\Chat\Models\ChatMessage;
use Spatie\LaravelData\Data;

final class ChatMessagesPageDTO extends Data
{
    /**
     * @param list<ChatMessage> $items    От старых к новым
     * @param bool              $has_more Есть ли сообщения старше первого из items
     */
    public function __construct(
        public array $items,
        public bool  $has_more,
    ) {
    }
}
