<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Requests;

use App\Modules\Chat\DTO\ForwardChatMessageDTO;
use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="ForwardChatMessageRequest",
 *     type="object",
 *     required={"message_id", "client_id"},
 *     @OA\Property(property="message_id", type="integer", example=42, description="Сообщение из любого чата, где состоит пользователь"),
 *     @OA\Property(property="client_id", type="string", format="uuid", description="UUID нового сообщения. Повтор запроса с тем же client_id не создаёт дубль")
 * )
 */
final class ForwardChatMessageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message_id' => ['required', 'integer', 'min:1'],
            'client_id'  => ['required', 'string', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Translator::group('fields.chat_forward');
    }

    public function toDTO(): ForwardChatMessageDTO
    {
        return new ForwardChatMessageDTO(
            message_id: $this->integer('message_id'),
            client_id: $this->string('client_id')->lower()->toString(),
        );
    }
}
