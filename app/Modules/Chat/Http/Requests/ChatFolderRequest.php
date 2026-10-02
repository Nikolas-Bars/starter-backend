<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Requests;

use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="ChatFolderRequest",
 *     type="object",
 *     required={"name"},
 *     @OA\Property(property="name", type="string", minLength=1, maxLength=32, example="Работа")
 * )
 */
final class ChatFolderRequest extends FormRequest
{
    public const MAX_NAME_LENGTH = 32;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:' . self::MAX_NAME_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Translator::group('fields.chat_folder');
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }

    protected function prepareForValidation(): void
    {
        if (\is_string($this->input('name'))) {
            $this->merge(['name' => \trim($this->input('name'))]);
        }
    }
}
