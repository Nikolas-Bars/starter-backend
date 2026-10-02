<?php

declare(strict_types=1);

namespace App\Modules\Chat\Repositories;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatFolder;
use App\Modules\Chat\Models\ChatMessage;
use App\Repositories\BaseRepository;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<ChatFolder>
 */
final class ChatFolderRepository extends BaseRepository
{
    private const CHAT_IDS = 'chats:chats.id';

    /**
     * @return class-string<ChatFolder>
     */
    public function model(): string
    {
        return ChatFolder::class;
    }

    /**
     * Папки пользователя в порядке создания — с id чатов и числом чатов с непрочитанным
     *
     * @return Collection<int, ChatFolder>
     */
    public function listForUser(int $userId): Collection
    {
        $query = $this->query()->with(self::CHAT_IDS)->withCount($this->unreadChatsCount($userId));
        $query->getQuery()->where('user_id', $userId)->orderBy('id');

        return $query->get();
    }

    public function findForUser(int $folderId, int $userId): ?ChatFolder
    {
        $query = $this->query()->with(self::CHAT_IDS)->withCount($this->unreadChatsCount($userId));
        $query->getQuery()->where('id', $folderId)->where('user_id', $userId);

        return $query->first();
    }

    /**
     * Перечитывает id чатов и число непрочитанных после изменений
     */
    public function reloadState(ChatFolder $folder): ChatFolder
    {
        return $folder->load(self::CHAT_IDS)->loadCount($this->unreadChatsCount($folder->user_id));
    }

    public function countForUser(int $userId): int
    {
        return $this->query()->where('user_id', $userId)->getQuery()->count();
    }

    public function store(int $userId, string $name): ChatFolder
    {
        return $this->create([
            'user_id' => $userId,
            'name'    => $name,
        ]);
    }

    public function rename(ChatFolder $folder, string $name): void
    {
        $folder->update(['name' => $name]);
    }

    public function delete(ChatFolder $folder): void
    {
        $folder->delete();
    }

    public function attachChat(ChatFolder $folder, int $chatId): void
    {
        $folder->chats()->syncWithoutDetaching([$chatId]);
    }

    public function detachChat(ChatFolder $folder, int $chatId): void
    {
        $folder->chats()->detach($chatId);
    }

    /**
     * @return array<string, Closure>
     */
    private function unreadChatsCount(int $userId): array
    {
        return [
            'chats as unread_chats_count' => static function (Builder $chats) use ($userId): void {
                /** @var Builder<Chat> $chats */
                $chats->whereHas('messages', static function (Builder $messages) use ($userId): void {
                    /** @var Builder<ChatMessage> $messages */
                    ChatMessageRepository::whereUnreadBy($messages, $userId);
                });
            },
        ];
    }
}
