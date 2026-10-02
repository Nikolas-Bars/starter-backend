<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Requests;

use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="MarkChatReadRequest",
 *     type="object",
 *     required={"message_id"},
 *     @OA\Property(property="message_id", type="integer", example=42, description="Последнее сообщение, которое пользователь увидел")
 * )
 */
final class MarkChatReadRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Translator::group('fields.chat_read');
    }

    public function messageId(): int
    {
        return $this->integer('message_id');
    }
}
