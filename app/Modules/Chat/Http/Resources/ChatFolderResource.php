<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Resources;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatFolder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="ChatFolderResource",
 *     type="object",
 *     description="Папка чатов текущего пользователя",
 *     @OA\Property(property="id", type="integer", example=3),
 *     @OA\Property(property="name", type="string", example="Работа"),
 *     @OA\Property(property="chat_ids", type="array", @OA\Items(type="integer"), example={7, 12}),
 *     @OA\Property(property="unread_chats_count", type="integer", example=1, description="Сколько чатов папки с непрочитанным")
 * )
 *
 * @mixin ChatFolder
 */
final class ChatFolderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'chat_ids'           => \array_values($this->chats->map(static fn(Chat $chat): int => $chat->id)->sort()->all()),
            'unread_chats_count' => $this->unread_chats_count ?? 0,
        ];
    }
}
