<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Requests;

use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="ChatTranslationNoteRequest",
 *     type="object",
 *     @OA\Property(property="note", type="string", nullable=true, maxLength=500, example="Бабушка и внук", description="Пусто или null — убрать заметку")
 * )
 */
final class ChatTranslationNoteRequest extends FormRequest
{
    public const MAX_LENGTH = 500;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['present', 'nullable', 'string', 'max:' . self::MAX_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Translator::group('fields.chat_translation_note');
    }

    public function note(): ?string
    {
        $note = $this->string('note')->toString();

        return $note === '' ? null : $note;
    }

    protected function prepareForValidation(): void
    {
        if (\is_string($this->input('note'))) {
            $this->merge(['note' => \trim($this->input('note'))]);
        }
    }
}
