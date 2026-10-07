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
     * @param Call|null                                   $call          Звонок для служебного сообщения; null — обычный текст
     * @param array{user_id: int|null, name: string}|null $forwardedFrom Автор оригинала, если сообщение пересланное
     */
    public function store(int $chatId, int $userId, string $clientId, string $body, ?Call $call = null, ?array $forwardedFrom = null): ChatMessage
    {
        $message = $this->create([
            'chat_id'                => $chatId,
            'user_id'                => $userId,
            'client_id'              => $clientId,
            'type'                   => $call === null ? ChatMessageTypeEnum::Text : ChatMessageTypeEnum::Call,
            'call_id'                => $call?->id,
            'body'                   => $body,
            'forwarded_from_user_id' => $forwardedFrom['user_id'] ?? null,
            'forwarded_from_name'    => $forwardedFrom['name'] ?? null,
        ]);

        // У нового сообщения реакций, вложений и переводов нет: не делаем за этим лишний запрос
        return $message
            ->setRelation('reactions', new Collection())
            ->setRelation('attachments', new Collection())
            ->setRelation('translations', new Collection())
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
        $query = $this->query()->where('chat_id', $chatId)->with(['reactions', 'call', 'attachments', 'translations']);

        if ($beforeId !== null) {
            $query->getQuery()->where('id', '<', $beforeId);
        }

        $query->getQuery()->orderByDesc('id')->limit($limit);

        return $query->get();
    }

    /**
     * Реакции и записи о вложениях удаляет каскад внешних ключей
     */
    public function delete(ChatMessage $message): void
    {
        $message->delete();
    }

    /**
     * Переводы старого текста больше не верны: удаляются, новый текст переведёт очередь
     */
    public function updateBody(ChatMessage $message, string $body): void
    {
        $message->forceFill(['body' => $body, 'body_locale' => null, 'edited_at' => $message->freshTimestamp()])->save();
        $message->translations()->getQuery()->delete();
    }

    /**
     * Сообщение для переводчика вместе с чатом; null — удалили, пока задача ждала очереди
     */
    public function findWithChat(int $messageId): ?ChatMessage
    {
        return $this->query()->with('chat')->whereKey($messageId)->first();
    }

    /**
     * Контекст разговора для переводчика: $limit текстовых сообщений перед $messageId, от старых к новым
     *
     * @return list<ChatMessage>
     */
    public function contextBefore(int $chatId, int $messageId, int $limit): array
    {
        $query = $this->query()->where('chat_id', $chatId)->where('type', ChatMessageTypeEnum::Text->value);
        $query->getQuery()->where('id', '<', $messageId)->where('body', '!=', '')->orderByDesc('id')->limit($limit);

        return \array_values(\array_reverse($query->get()->all()));
    }

    /**
     * @param array<string, string> $translations locale => текст
     */
    public function saveTranslations(ChatMessage $message, ?string $bodyLocale, array $translations): ChatMessage
    {
        $message->forceFill(['body_locale' => $bodyLocale])->save();

        foreach ($translations as $locale => $body) {
            $message->translations()->updateOrCreate(['locale' => $locale], ['body' => $body]);
        }

        return $message->load('translations');
    }

    public function loadForResource(ChatMessage $message): ChatMessage
    {
        return $message->load(['reactions', 'call', 'attachments', 'translations']);
    }

    public function findForResource(int $messageId): ?ChatMessage
    {
        return $this->query()->with(['reactions', 'call', 'attachments', 'translations'])->whereKey($messageId)->first();
    }

    /**
     * Писал ли в чате кто-то, кроме $userId, после сообщения $messageId
     */
    public function hasOthersAfter(int $chatId, int $messageId, int $userId): bool
    {
        $query = $this->query()->where('chat_id', $chatId)->getQuery();

        return $query->where('id', '>', $messageId)->where('user_id', '!=', $userId)->exists();
    }

    public function latestInChat(int $chatId): ?ChatMessage
    {
        $query = $this->query()->where('chat_id', $chatId)->with(['reactions', 'call', 'attachments', 'translations']);
        $query->getQuery()->orderByDesc('id');

        return $query->first();
    }

    public function findWithAttachments(int $messageId): ?ChatMessage
    {
        return $this->query()->with('attachments')->whereKey($messageId)->first();
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
