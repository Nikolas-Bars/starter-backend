<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Resources;

use App\Modules\Chat\DTO\ChatReadStateDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="ChatReadStateResource",
 *     type="object",
 *     description="Докуда пользователь прочитал чат. То же приходит участникам событием chat.read",
 *     @OA\Property(property="chat_id", type="integer", example=7),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="last_read_message_id", type="integer", example=42),
 *     @OA\Property(property="unread_count", type="integer", example=0, description="Сколько осталось непрочитанных у user_id")
 * )
 *
 * @mixin ChatReadStateDTO
 */
final class ChatReadStateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'chat_id'              => $this->chat_id,
            'user_id'              => $this->user_id,
            'last_read_message_id' => $this->last_read_message_id,
            'unread_count'         => $this->unread_count,
        ];
    }
}
