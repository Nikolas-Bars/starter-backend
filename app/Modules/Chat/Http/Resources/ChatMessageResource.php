<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Resources;

use App\Modules\Chat\Models\ChatAttachment;
use App\Modules\Chat\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="ChatMessageReaction",
 *     type="object",
 *     description="Одна реакция и кто её поставил",
 *     @OA\Property(property="emoji", type="string", enum={"👍", "❤️", "😂", "😮", "😢", "🔥", "🙏", "👎"}),
 *     @OA\Property(property="user_ids", type="array", @OA\Items(type="integer"), example={1, 2})
 * )
 *
 * @OA\Schema(
 *     schema="ChatMessageResource",
 *     type="object",
 *     description="Сообщение в чате",
 *     @OA\Property(property="id", type="integer", example=42),
 *     @OA\Property(property="chat_id", type="integer", example=7),
 *     @OA\Property(property="user_id", type="integer", example=1, description="Автор"),
 *     @OA\Property(property="client_id", type="string", format="uuid", description="UUID, выбранный отправителем"),
 *     @OA\Property(property="type", type="string", enum={"text", "call"}, description="call — служебное сообщение о звонке, body пустой"),
 *     @OA\Property(property="body", type="string", example="Привет!", description="Оригинал, как написал автор"),
 *     @OA\Property(property="body_locale", type="string", nullable=true, example="ru", description="Язык оригинала по мнению переводчика; null — ещё не переводили"),
 *     @OA\Property(
 *         property="translations",
 *         type="object",
 *         description="Перевод body на языки интерфейса участников (users.locale), кроме языка оригинала. Пусто — переводить не нужно или перевод ещё идёт: он придёт событием chat.message_translated",
 *         additionalProperties=@OA\Schema(type="string"),
 *         example={"vi": "Xin chào!"}
 *     ),
 *     @OA\Property(
 *         property="forwarded_from",
 *         type="object",
 *         nullable=true,
 *         description="Пересланное сообщение: автор оригинала (имя — на момент пересылки; user_id пуст, если автора удалили)",
 *         @OA\Property(property="user_id", type="integer", nullable=true, example=3),
 *         @OA\Property(property="name", type="string", example="Мария")
 *     ),
 *     @OA\Property(
 *         property="call",
 *         type="object",
 *         nullable=true,
 *         description="Только для type = call. Автор сообщения — звонивший",
 *         @OA\Property(property="id", type="integer", example=12),
 *         @OA\Property(property="status", type="string", enum={"rejected", "missed", "busy", "unavailable", "ended"}),
 *         @OA\Property(property="duration_seconds", type="integer", nullable=true, description="Длительность разговора; null — не ответили")
 *     ),
 *     @OA\Property(
 *         property="reactions",
 *         type="array",
 *         description="В порядке, в каком реакции впервые появились",
 *         @OA\Items(ref="#/components/schemas/ChatMessageReaction")
 *     ),
 *     @OA\Property(
 *         property="attachments",
 *         type="array",
 *         description="Файлы сообщения; тогда body — необязательная подпись",
 *         @OA\Items(ref="#/components/schemas/ChatAttachmentResource")
 *     ),
 *     @OA\Property(property="edited_at", type="string", format="date-time", nullable=true, description="Автор менял текст; null — не менял"),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true)
 * )
 *
 * @mixin ChatMessage
 */
final class ChatMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'chat_id'        => $this->chat_id,
            'user_id'        => $this->user_id,
            'client_id'      => $this->client_id,
            'type'           => $this->type->value,
            'body'           => $this->body,
            'body_locale'    => $this->body_locale,
            'translations'   => (object)$this->translations->pluck('body', 'locale')->all(),
            'forwarded_from' => $this->forwarded_from_name === null ? null : [
                'user_id' => $this->forwarded_from_user_id,
                'name'    => $this->forwarded_from_name,
            ],
            'call' => $this->call === null ? null : [
                'id'               => $this->call->id,
                'status'           => $this->call->status->value,
                'duration_seconds' => $this->call->durationSeconds(),
            ],
            'reactions'   => $this->groupedReactions(),
            'attachments' => $this->attachments->map(
                static fn(ChatAttachment $attachment): array => ChatAttachmentResource::make($attachment)->resolve(),
            )->values()->all(),
            'edited_at'  => $this->edited_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{emoji: string, user_ids: list<int>}>
     */
    private function groupedReactions(): array
    {
        /** @var array<string, list<int>> $groups */
        $groups = [];

        foreach ($this->reactions as $reaction) {
            $groups[$reaction->emoji->value][] = $reaction->user_id;
        }

        $reactions = [];

        foreach ($groups as $emoji => $userIds) {
            $reactions[] = ['emoji' => $emoji, 'user_ids' => $userIds];
        }

        return $reactions;
    }
}
