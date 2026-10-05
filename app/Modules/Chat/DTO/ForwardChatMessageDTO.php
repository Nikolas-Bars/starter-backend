<?php

declare(strict_types=1);

namespace App\Modules\Chat\DTO;

use Spatie\LaravelData\Data;

final class ForwardChatMessageDTO extends Data
{
    /**
     * @param int    $message_id Пересылаемое сообщение из любого своего чата
     * @param string $client_id  UUID нового сообщения: повтор запроса не создаёт дубль
     */
    public function __construct(
        public int $message_id,
        public string $client_id,
    ) {
    }
}
