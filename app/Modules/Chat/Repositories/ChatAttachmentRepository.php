<?php

declare(strict_types=1);

namespace App\Modules\Chat\Repositories;

use App\Modules\Chat\Enums\ChatAttachmentKindEnum;
use App\Modules\Chat\Enums\ChatAttachmentStatusEnum;
use App\Modules\Chat\Models\ChatAttachment;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * @extends BaseRepository<ChatAttachment>
 */
final class ChatAttachmentRepository extends BaseRepository
{
    /**
     * @return class-string<ChatAttachment>
     */
    public function model(): string
    {
        return ChatAttachment::class;
    }

    public function store(
        int $userId,
        ChatAttachmentKindEnum $kind,
        string $originalName,
        string $mime,
        int $size,
        string $path,
    ): ChatAttachment {
        return $this->create([
            'user_id'       => $userId,
            'kind'          => $kind,
            'status'        => $kind->needsProcessing() ? ChatAttachmentStatusEnum::Processing : ChatAttachmentStatusEnum::Ready,
            'original_name' => $originalName,
            'mime'          => $mime,
            'size'          => $size,
            'path'          => $path,
        ]);
    }

    /**
     * Сколько байт занимают все вложения
     */
    public function totalSize(): int
    {
        return (int)$this->query()->getQuery()->sum('size');
    }

    /**
     * Файл по пути на диске: и сам файл, и его превью
     */
    public function findByPath(string $path): ?ChatAttachment
    {
        return $this->query()->where('path', $path)->orWhere('thumb_path', $path)->first();
    }

    /**
     * Свои, ещё не отправленные и не испорченные вложения из списка — в порядке id.
     * Строки блокируются до конца транзакции: обработка файла дождётся отправки
     * и увидит, к какому сообщению он привязан.
     *
     * @param list<int> $ids
     *
     * @return Collection<int, ChatAttachment>
     */
    public function findSendable(int $userId, array $ids): Collection
    {
        $query = $this->query();
        $query->getQuery()
            ->whereIn('id', $ids)
            ->where('user_id', $userId)
            ->whereNull('message_id')
            ->where('status', '!=', ChatAttachmentStatusEnum::Failed->value)
            ->orderBy('id')
            ->lockForUpdate();

        return $query->get();
    }

    /**
     * @param list<int> $ids
     */
    public function attachToMessage(array $ids, int $messageId): void
    {
        $this->query()->getQuery()->whereIn('id', $ids)->update(['message_id' => $messageId]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(ChatAttachment $attachment, array $attributes): ChatAttachment
    {
        $attachment->update($attributes);

        return $attachment;
    }

    /**
     * Сообщение и чат вложения по базе, а не по модели в памяти: сообщение могли отправить,
     * пока файл обрабатывался
     *
     * @return array{message_id: int, chat_id: int}|null
     */
    public function findSentTo(int $attachmentId): ?array
    {
        $row = $this->query()->getQuery()
            ->join('chat_messages', 'chat_messages.id', '=', 'chat_attachments.message_id')
            ->where('chat_attachments.id', $attachmentId)
            ->first(['chat_attachments.message_id', 'chat_messages.chat_id']);

        if ($row === null || !\is_numeric($row->message_id ?? null) || !\is_numeric($row->chat_id ?? null)) {
            return null;
        }

        return ['message_id' => (int)$row->message_id, 'chat_id' => (int)$row->chat_id];
    }

    /**
     * Загруженные раньше $before и так и не отправленные
     *
     * @return Collection<int, ChatAttachment>
     */
    public function unsentBefore(Carbon $before): Collection
    {
        $query = $this->query();
        $query->getQuery()->whereNull('message_id')->where('created_at', '<', $before);

        return $query->get();
    }

    /**
     * Отправленные раньше $before — для ручной очистки места
     *
     * @return Collection<int, ChatAttachment>
     */
    public function sentBefore(Carbon $before): Collection
    {
        $query = $this->query();
        $query->getQuery()->whereNotNull('message_id')->where('created_at', '<', $before);

        return $query->get();
    }

    /**
     * @param list<int> $ids
     */
    public function deleteMany(array $ids): void
    {
        $this->query()->getQuery()->whereIn('id', $ids)->delete();
    }
}
