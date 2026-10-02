<?php

declare(strict_types=1);

namespace App\Modules\Chat\DTO;

use Spatie\LaravelData\Data;

final class SendChatMessageDTO extends Data
{
    /**
     * @param string $client_id UUID, выбранный клиентом: по нему повторная отправка находит уже сохранённое
     */
    public function __construct(
        public string $body,
        public string $client_id,
    ) {
    }
}
