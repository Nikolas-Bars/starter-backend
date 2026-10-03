<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Requests;

use App\Modules\Chat\DTO\SendChatMessageDTO;
use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;

/**
 * @OA\Schema(
 *     schema="SendChatMessageRequest",
 *     type="object",
 *     required={"client_id"},
 *     @OA\Property(property="body", type="string", maxLength=4000, example="Привет!", description="Обязателен, если нет attachment_ids; с файлами — подпись"),
 *     @OA\Property(
 *         property="client_id",
 *         type="string",
 *         format="uuid",
 *         description="UUID сообщения, выбранный клиентом. Повтор запроса с тем же client_id не создаёт дубль"
 *     ),
 *     @OA\Property(
 *         property="attachment_ids",
 *         type="array",
 *         maxItems=10,
 *         description="Свои ещё не отправленные файлы из POST /api/attachments; можно отправлять, пока они обрабатываются",
 *         @OA\Items(type="integer", example=5)
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
            'body'             => ['required_without:attachment_ids', 'nullable', 'string', 'max:' . self::MAX_LENGTH],
            'client_id'        => ['required', 'string', 'uuid'],
            'attachment_ids'   => ['sometimes', 'array', 'max:' . Config::integer('attachments.max_per_message')],
            'attachment_ids.*' => ['integer', 'distinct'],
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
        /** @var list<int|string> $attachmentIds */
        $attachmentIds = \array_values($this->array('attachment_ids'));

        return new SendChatMessageDTO(
            body: $this->string('body')->toString(),
            client_id: $this->string('client_id')->lower()->toString(),
            attachment_ids: \array_map(\intval(...), $attachmentIds),
        );
    }

    protected function prepareForValidation(): void
    {
        if (\is_string($this->input('body'))) {
            $this->merge(['body' => \trim($this->input('body'))]);
        }
    }
}
