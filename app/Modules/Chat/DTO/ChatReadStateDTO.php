<?php

declare(strict_types=1);

namespace App\Modules\Chat\DTO;

use Spatie\LaravelData\Data;

final class ChatReadStateDTO extends Data
{
    /**
     * @param int $last_read_message_id Всё до этого сообщения включительно пользователь прочитал
     * @param int $unread_count         Сколько у этого пользователя осталось непрочитанных
     */
    public function __construct(
        public int $chat_id,
        public int $user_id,
        public int $last_read_message_id,
        public int $unread_count,
    ) {
    }
}
