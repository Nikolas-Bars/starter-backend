<?php

declare(strict_types=1);

namespace App\Modules\Chat\Repositories;

use App\Modules\Chat\Enums\ChatReactionEnum;
use App\Modules\Chat\Models\ChatMessageReaction;
use App\Repositories\BaseRepository;

/**
 * @extends BaseRepository<ChatMessageReaction>
 */
final class ChatMessageReactionRepository extends BaseRepository
{
    /**
     * @return class-string<ChatMessageReaction>
     */
    public function model(): string
    {
        return ChatMessageReaction::class;
    }

    public function find(int $messageId, int $userId): ?ChatMessageReaction
    {
        return $this->query()->where('message_id', $messageId)->where('user_id', $userId)->first();
    }

    public function store(int $messageId, int $userId, ChatReactionEnum $emoji): ChatMessageReaction
    {
        return $this->create([
            'message_id' => $messageId,
            'user_id'    => $userId,
            'emoji'      => $emoji,
        ]);
    }

    public function updateEmoji(ChatMessageReaction $reaction, ChatReactionEnum $emoji): void
    {
        $reaction->update(['emoji' => $emoji]);
    }

    public function delete(ChatMessageReaction $reaction): void
    {
        $reaction->delete();
    }
}
