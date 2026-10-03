<?php

declare(strict_types=1);

namespace App\Modules\Chat\DTO;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

final class UploadChatAttachmentDTO extends Data
{
    /**
     * @param string $original_name Имя у отправителя, уже очищенное
     * @param bool   $voice         Записано в приложении как голосовое
     * @param bool   $as_file       Не сжимать: отправить как обычный файл
     */
    public function __construct(
        public UploadedFile $file,
        public string $original_name,
        public bool $voice = false,
        public bool $as_file = false,
    ) {
    }
}
