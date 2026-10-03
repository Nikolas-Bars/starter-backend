<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Requests;

use App\Modules\Chat\DTO\UploadChatAttachmentDTO;
use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;

/**
 * @OA\Schema(
 *     schema="UploadChatAttachmentRequest",
 *     type="object",
 *     required={"file"},
 *     @OA\Property(property="file", type="string", format="binary", description="До 50 МБ"),
 *     @OA\Property(property="voice", type="boolean", description="Записанное голосовое: сохранится как голосовое, если это звук"),
 *     @OA\Property(property="as_file", type="boolean", description="Отправить как файл: фото и видео не сжимаются, открываются только на скачивание")
 * )
 */
final class UploadChatAttachmentRequest extends FormRequest
{
    private const int NAME_MAX_LENGTH = 200;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file'    => ['required', 'file', 'max:' . \intdiv(Config::integer('attachments.max_file_bytes'), 1024)],
            'voice'   => ['sometimes', 'boolean'],
            'as_file' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Translator::group('fields.chat_attachment');
    }

    public function toDTO(): UploadChatAttachmentDTO
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return new UploadChatAttachmentDTO(
            file: $file,
            original_name: self::cleanName($file->getClientOriginalName()),
            voice: $this->boolean('voice'),
            as_file: $this->boolean('as_file'),
        );
    }

    /**
     * Имя показывается собеседнику и попадает в Content-Disposition: без путей и управляющих символов
     */
    private static function cleanName(string $name): string
    {
        $name = (string)\preg_replace('/[\x00-\x1F\x7F]+/u', '', \basename(\str_replace('\\', '/', $name)));
        $name = \trim(\mb_substr($name, -self::NAME_MAX_LENGTH));

        return $name === '' || $name === '.' || $name === '..' ? 'file' : $name;
    }
}
