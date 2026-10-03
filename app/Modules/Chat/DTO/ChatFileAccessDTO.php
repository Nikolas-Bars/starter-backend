<?php

declare(strict_types=1);

namespace App\Modules\Chat\DTO;

use Spatie\LaravelData\Data;

final class ChatFileAccessDTO extends Data
{
    /**
     * Заголовки, с которыми файл можно отдавать: Content-Type не из расширения, а из базы;
     * обычные файлы — только на скачивание, чтобы HTML или SVG не открылся на нашем домене.
     *
     * @param string $path Путь на диске вложений
     */
    public function __construct(
        public string $path,
        public string $content_type,
        public string $content_disposition,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [
            'Content-Type'            => $this->content_type,
            'Content-Disposition'     => $this->content_disposition,
            'X-Content-Type-Options'  => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control'           => 'private, max-age=86400',
        ];
    }
}
