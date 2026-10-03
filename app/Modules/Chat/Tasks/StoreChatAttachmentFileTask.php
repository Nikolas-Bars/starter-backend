<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Enums\ChatAttachmentKindEnum;
use App\Tasks\BaseTask;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class StoreChatAttachmentFileTask extends BaseTask
{
    /**
     * Кладёт загруженный файл на диск вложений под случайным именем — по имени файл не угадать.
     * Файл, который ещё будут сжимать, получает расширение .src: результат ляжет рядом.
     *
     * @return string Путь на диске attachments
     */
    public function run(UploadedFile $file, ChatAttachmentKindEnum $kind): string
    {
        $name = Date::now()->format('Y/m') . '/' . Str::lower(Str::random(40)) . ($kind->needsProcessing() ? '.src' : '.bin');

        Storage::disk(Config::string('attachments.disk'))->putFileAs(\dirname($name), $file, \basename($name));

        return $name;
    }
}
