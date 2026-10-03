<?php

declare(strict_types=1);

namespace App\Modules\Chat\Repositories;

use App\Modules\Call\Models\Call;
use App\Modules\Chat\Enums\ChatMessageTypeEnum;
use App\Modules\Chat\Models\ChatMessage;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * @extends BaseRepository<ChatMessage>
 */
final class ChatMessageRepository extends BaseRepository
{
    /**
     * @return class-string<ChatMessage>
     */
    public function model(): string
    {
        return ChatMessage::class;
    }

    public function findByClientId(int $userId, string $clientId): ?ChatMessage
    {
        return $this->query()->where('user_id', $userId)->where('client_id', $clientId)->first();
    }

    /**
     * @param Call|null $call Звонок для служебного сообщения; null — обычный текст
     */
    public function store(int $chatId, int $userId, string $clientId, string $body, ?Call $call = null): ChatMessage
    {
        $message = $this->create([
            'chat_id'   => $chatId,
            'user_id'   => $userId,
            'client_id' => $clientId,
            'type'      => $call === null ? ChatMessageTypeEnum::Text : ChatMessageTypeEnum::Call,
            'call_id'   => $call?->id,
            'body'      => $body,
        ]);

        // У нового сообщения реакций и вложений нет: не делаем за этим лишний запрос
        return $message
            ->setRelation('reactions', new Collection())
            ->setRelation('attachments', new Collection())
            ->setRelation('call', $call);
    }

    public function loadReactions(ChatMessage $message): ChatMessage
    {
        return $message->load('reactions');
    }

    /**
     * Последние $limit сообщений чата старше $beforeId (null — самые новые), от новых к старым.
     *
     * @return Collection<int, ChatMessage>
     */
    public function latestBefore(int $chatId, ?int $beforeId, int $limit): Collection
    {
        $query = $this->query()->where('chat_id', $chatId)->with(['reactions', 'call', 'attachments']);

        if ($beforeId !== null) {
            $query->getQuery()->where('id', '<', $beforeId);
        }

        $query->getQuery()->orderByDesc('id')->limit($limit);

        return $query->get();
    }

    public function findInChat(int $chatId, int $messageId): ?ChatMessage
    {
        return $this->query()->where('chat_id', $chatId)->where('id', $messageId)->first();
    }

    /**
     * Сообщения собеседников, которые пользователь ещё не прочитал (по его отметке в chat_members)
     *
     * @param Builder<ChatMessage> $messages
     */
    public static function whereUnreadBy(Builder $messages, int $userId): void
    {
        $messages->getQuery()
            ->where('chat_messages.user_id', '!=', $userId)
            ->where('chat_messages.id', '>', static function (QueryBuilder $lastRead) use ($userId): void {
                $lastRead->select('last_read_message_id')
                    ->from('chat_members')
                    ->whereColumn('chat_members.chat_id', 'chat_messages.chat_id')
                    ->where('chat_members.user_id', $userId);
            });
    }

    /**
     * Сколько сообщений собеседников новее $lastReadMessageId
     */
    public function countUnread(int $chatId, int $userId, int $lastReadMessageId): int
    {
        $query = $this->query()->where('chat_id', $chatId)->getQuery();

        return $query->where('user_id', '!=', $userId)->where('id', '>', $lastReadMessageId)->count();
    }
}
