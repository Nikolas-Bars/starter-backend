<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Requests;

use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="EditChatMessageRequest",
 *     type="object",
 *     required={"body"},
 *     @OA\Property(property="body", type="string", maxLength=4000, example="Привет!", description="Новый текст; пробелы по краям обрезаются")
 * )
 */
final class EditChatMessageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:' . SendChatMessageRequest::MAX_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Translator::group('fields.chat_message');
    }

    public function body(): string
    {
        return $this->string('body')->toString();
    }

    protected function prepareForValidation(): void
    {
        if (\is_string($this->input('body'))) {
            $this->merge(['body' => \trim($this->input('body'))]);
        }
    }
}
