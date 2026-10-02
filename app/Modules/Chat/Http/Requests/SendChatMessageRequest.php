<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Requests;

use App\Modules\Chat\DTO\SendChatMessageDTO;
use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="SendChatMessageRequest",
 *     type="object",
 *     required={"body", "client_id"},
 *     @OA\Property(property="body", type="string", minLength=1, maxLength=4000, example="Привет!"),
 *     @OA\Property(
 *         property="client_id",
 *         type="string",
 *         format="uuid",
 *         description="UUID сообщения, выбранный клиентом. Повтор запроса с тем же client_id не создаёт дубль"
 *     )
 * )
 */
final class SendChatMessageRequest extends FormRequest
{
    public const MAX_LENGTH = 4000;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body'      => ['required', 'string', 'max:' . self::MAX_LENGTH],
            'client_id' => ['required', 'string', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Translator::group('fields.chat_message');
    }

    public function toDTO(): SendChatMessageDTO
    {
        return new SendChatMessageDTO(
            body: $this->string('body')->toString(),
            client_id: $this->string('client_id')->lower()->toString(),
        );
    }

    protected function prepareForValidation(): void
    {
        if (\is_string($this->input('body'))) {
            $this->merge(['body' => \trim($this->input('body'))]);
        }
    }
}
