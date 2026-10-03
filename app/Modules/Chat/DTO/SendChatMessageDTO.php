<?php

declare(strict_types=1);

namespace App\Modules\Chat\DTO;

use Spatie\LaravelData\Data;

final class SendChatMessageDTO extends Data
{
    /**
     * @param string    $body           С файлами — подпись, может быть пустой
     * @param string    $client_id      UUID, выбранный клиентом: по нему повторная отправка находит уже сохранённое
     * @param list<int> $attachment_ids Загруженные заранее файлы
     */
    public function __construct(
        public string $body,
        public string $client_id,
        public array $attachment_ids = [],
    ) {
    }
}
