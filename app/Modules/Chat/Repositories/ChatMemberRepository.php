<?php

declare(strict_types=1);

namespace App\Modules\Chat\Repositories;

use App\Modules\Chat\Models\ChatMember;
use App\Repositories\BaseRepository;

/**
 * @extends BaseRepository<ChatMember>
 */
final class ChatMemberRepository extends BaseRepository
{
    /**
     * @return class-string<ChatMember>
     */
    public function model(): string
    {
        return ChatMember::class;
    }

    public function find(int $chatId, int $userId): ?ChatMember
    {
        return $this->query()->where('chat_id', $chatId)->where('user_id', $userId)->first();
    }

    public function store(int $chatId, int $userId): ChatMember
    {
        return $this->create([
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    /**
     * @return list<int>
     */
    public function userIds(int $chatId): array
    {
        $userIds = [];

        foreach ($this->query()->where('chat_id', $chatId)->get(['user_id']) as $member) {
            $userIds[] = $member->user_id;
        }

        return $userIds;
    }

    /**
     * @return list<ChatMember>
     */
    public function withUsers(int $chatId): array
    {
        return \array_values($this->query()->where('chat_id', $chatId)->with('user')->get()->all());
    }

    public function updateLastRead(ChatMember $member, int $messageId): ChatMember
    {
        $member->update(['last_read_message_id' => $messageId]);

        return $member;
    }
}
