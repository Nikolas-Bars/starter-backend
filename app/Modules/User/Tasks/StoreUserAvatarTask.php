<?php

declare(strict_types=1);

namespace App\Modules\User\Tasks;

use App\Modules\User\Exceptions\InvalidAvatarException;
use App\Services\MediaProcessor;
use App\Tasks\BaseTask;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class StoreUserAvatarTask extends BaseTask
{
    public function __construct(
        private readonly MediaProcessor $mediaProcessor,
    ) {
    }

    /**
     * Сжимает фото в квадратную аватарку и кладёт на диск вложений под случайным именем
     *
     * @throws InvalidAvatarException
     *
     * @return string Путь на диске attachments
     */
    public function run(UploadedFile $file): string
    {
        $disk = Storage::disk(Config::string('attachments.disk'));
        $path = 'avatars/' . Date::now()->format('Y/m') . '/' . Str::lower(Str::random(40)) . '.jpg';
        $temp = \tempnam(\sys_get_temp_dir(), 'avatar');

        if ($temp === false) {
            throw new InvalidAvatarException();
        }

        try {
            $this->mediaProcessor->avatar($file->getRealPath(), $temp);
            $disk->putFileAs(\dirname($path), $temp, \basename($path));
        } catch (Throwable) {
            throw new InvalidAvatarException();
        } finally {
            @\unlink($temp);
        }

        return $path;
    }
}
