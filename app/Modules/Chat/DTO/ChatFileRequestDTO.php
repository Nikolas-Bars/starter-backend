<?php

declare(strict_types=1);

namespace App\Modules\Chat\DTO;

use Spatie\LaravelData\Data;

final class ChatFileRequestDTO extends Data
{
    /**
     * @param string $path Путь на диске вложений из ссылки /api/files/{path}
     */
    public function __construct(
        public string $path,
        public string $expires,
        public string $signature,
    ) {
    }
}
