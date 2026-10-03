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
 *     @OA\Property(property="body", type="string", example="Привет!"),
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
            'id'        => $this->id,
            'chat_id'   => $this->chat_id,
            'user_id'   => $this->user_id,
            'client_id' => $this->client_id,
            'type'      => $this->type->value,
            'body'      => $this->body,
            'call'      => $this->call === null ? null : [
                'id'               => $this->call->id,
                'status'           => $this->call->status->value,
                'duration_seconds' => $this->call->durationSeconds(),
            ],
            'reactions'   => $this->groupedReactions(),
            'attachments' => $this->attachments->map(
                static fn(ChatAttachment $attachment): array => ChatAttachmentResource::make($attachment)->resolve(),
            )->values()->all(),
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
