<?php

declare(strict_types=1);

namespace App\Modules\Chat\Repositories;

use App\Modules\Chat\Enums\ChatTypeEnum;
use App\Modules\Chat\Models\Chat;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Chat>
 */
final class ChatRepository extends BaseRepository
{
    /**
     * @return class-string<Chat>
     */
    public function model(): string
    {
        return Chat::class;
    }

    public function findDirectByKey(string $directKey): ?Chat
    {
        return $this->query()->where('direct_key', $directKey)->first();
    }

    public function storeDirect(string $directKey): Chat
    {
        return $this->create([
            'type'       => ChatTypeEnum::Direct,
            'direct_key' => $directKey,
        ]);
    }

    /**
     * Чат, если пользователь в нём участвует, — с участниками, последним сообщением
     * и числом непрочитанных этим пользователем.
     */
    public function findForMember(int $chatId, int $userId): ?Chat
    {
        $query = $this->forMember($userId);
        $query->getQuery()->where('chats.id', $chatId);

        return $query->first();
    }

    /**
     * Чаты пользователя, где уже есть переписка, — сверху тот, где писали последним.
     * С $folderId — только чаты этой папки.
     *
     * @return LengthAwarePaginator<int, Chat>
     */
    public function paginateForMember(int $userId, ?int $folderId, int $perPage): LengthAwarePaginator
    {
        $query = $this->forMember($userId);

        if ($folderId !== null) {
            $query->whereHas('folders', static function (Builder $folders) use ($folderId): void {
                $folders->getQuery()->where('chat_folders.id', $folderId);
            });
        }

        $query->getQuery()->whereNotNull('chats.last_message_id')->orderByDesc('chats.last_message_id');

        return $query->paginate($perPage);
    }

    public function updateLastMessage(Chat $chat, int $messageId): void
    {
        $chat->update(['last_message_id' => $messageId]);
    }

    /**
     * @return Builder<Chat>
     */
    private function forMember(int $userId): Builder
    {
        return $this->query()
            ->whereHas('members', static function (Builder $members) use ($userId): void {
                $members->getQuery()->where('user_id', $userId);
            })
            ->with(['members.user', 'lastMessage.reactions', 'lastMessage.call', 'lastMessage.attachments'])
            ->withCount(['messages as unread_count' => static function (Builder $messages) use ($userId): void {
                ChatMessageRepository::whereUnreadBy($messages, $userId);
            }]);
    }
}
