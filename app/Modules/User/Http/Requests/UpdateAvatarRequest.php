<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Requests;

use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * @OA\Schema(
 *     schema="UpdateAvatarRequest",
 *     type="object",
 *     required={"avatar"},
 *     @OA\Property(property="avatar", type="string", format="binary", description="JPEG, PNG, WebP, HEIC или GIF до 20 МБ: сервер обрежет до квадрата по центру")
 * )
 */
final class UpdateAvatarRequest extends FormRequest
{
    private const int MAX_KILOBYTES = 20 * 1024;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'avatar' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif,gif', 'max:' . self::MAX_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Translator::group('fields.profile');
    }

    public function avatar(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('avatar');

        return $file;
    }
}
