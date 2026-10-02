<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Requests;

use App\Modules\Chat\DTO\ListChatMessagesDTO;
use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Parameter(
 *     parameter="ListChatMessagesRequest.before_id",
 *     name="before_id",
 *     in="query",
 *     required=false,
 *     description="Вернуть сообщения старше этого (листание вверх). Без него — самые новые",
 *     @OA\Schema(type="integer", minimum=1, example=42)
 * )
 */
final class ListChatMessagesRequest extends FormRequest
{
    private const PAGE_SIZE = 50;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'before_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Translator::group('fields.chat_history');
    }

    public function toDTO(): ListChatMessagesDTO
    {
        return new ListChatMessagesDTO(
            before_id: $this->filled('before_id') ? $this->integer('before_id') : null,
            limit: self::PAGE_SIZE,
        );
    }
}
