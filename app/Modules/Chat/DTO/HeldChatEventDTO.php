<?php

declare(strict_types=1);

namespace App\Modules\Chat\DTO;

use Spatie\LaravelData\Data;

/**
 * Событие о сообщении, которое собеседники с другим языком получат вместе с переводом
 */
final class HeldChatEventDTO extends Data
{
    /**
     * @param string    $event    chat.message или chat.message_updated
     * @param list<int> $user_ids Кто ждёт перевода
     * @param string    $token    Событие отдаётся один раз: переводом или по таймеру, кто раньше
     */
    public function __construct(
        public string $event,
        public array $user_ids,
        public string $token,
    ) {
    }
}
