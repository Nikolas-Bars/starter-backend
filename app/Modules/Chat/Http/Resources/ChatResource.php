<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Resources;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatMember;
use App\Modules\User\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="ChatResource",
 *     type="object",
 *     description="Чат глазами текущего пользователя",
 *     @OA\Property(property="id", type="integer", example=7),
 *     @OA\Property(property="type", type="string", enum={"direct"}),
 *     @OA\Property(property="peer", ref="#/components/schemas/UserResource", description="Собеседник в личном чате"),
 *     @OA\Property(property="last_message", ref="#/components/schemas/ChatMessageResource", nullable=true),
 *     @OA\Property(property="unread_count", type="integer", example=3, description="Непрочитанные текущим пользователем"),
 *     @OA\Property(property="last_read_message_id", type="integer", example=40, description="Докуда прочитал текущий пользователь; 0 — ничего"),
 *     @OA\Property(property="peer_last_read_message_id", type="integer", example=42, description="Докуда прочитал собеседник: свои сообщения с id не больше этого — прочитаны"),
 *     @OA\Property(property="translation_note", type="string", nullable=true, example="Бабушка и внук", description="Для автоперевода: кто кем друг другу приходится"),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true)
 * )
 *
 * @mixin Chat
 */
final class ChatResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerId = $request->user()?->getAuthIdentifier();

        $own  = $this->members->first(static fn(ChatMember $member): bool => $member->user_id === $viewerId);
        $peer = $this->members->first(static fn(ChatMember $member): bool => $member->user_id !== $viewerId);

        return [
            'id'                        => $this->id,
            'type'                      => $this->type->value,
            'peer'                      => $peer === null ? null : UserResource::make($peer->user),
            'last_message'              => $this->lastMessage === null ? null : ChatMessageResource::make($this->lastMessage),
            'unread_count'              => $this->unread_count ?? 0,
            'last_read_message_id'      => $own->last_read_message_id ?? 0,
            'peer_last_read_message_id' => $peer->last_read_message_id ?? 0,
            'translation_note'          => $this->translation_note,
            'created_at'                => $this->created_at?->toIso8601String(),
        ];
    }
}
