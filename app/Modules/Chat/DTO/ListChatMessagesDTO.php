<?php

declare(strict_types=1);

namespace App\Modules\Chat\DTO;

use Spatie\LaravelData\Data;

final class ListChatMessagesDTO extends Data
{
    /**
     * @param int|null $before_id Сообщения старше этого; null — самые новые
     */
    public function __construct(
        public ?int $before_id = null,
        public int  $limit = 50,
    ) {
    }
}
