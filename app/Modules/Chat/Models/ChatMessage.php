<?php

declare(strict_types=1);

namespace App\Modules\Chat\Models;

use App\Modules\Call\Models\Call;
use App\Modules\Chat\Database\Factories\ChatMessageFactory;
use App\Modules\Chat\Enums\ChatMessageTypeEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property      int                                  $id
 * @property      int                                  $chat_id
 * @property      int                                  $user_id                Автор
 * @property      string                               $client_id              UUID, выбранный клиентом: повторная отправка не создаёт дубль
 * @property      ChatMessageTypeEnum                  $type
 * @property      int|null                             $call_id                Для type = call
 * @property      string                               $body                   У служебных сообщений пустой; у сообщения с файлами — подпись или пусто
 * @property      int|null                             $forwarded_from_user_id Пересланное: автор оригинала
 * @property      string|null                          $forwarded_from_name    Пересланное: имя автора оригинала на момент пересылки
 * @property-read Carbon|null                          $created_at
 * @property-read Carbon|null                          $updated_at
 * @property-read Collection<int, ChatMessageReaction> $reactions
 * @property-read Collection<int, ChatAttachment>      $attachments
 * @property-read Call|null                            $call
 */
final class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'chat_id',
        'user_id',
        'client_id',
        'type',
        'call_id',
        'body',
        'forwarded_from_user_id',
        'forwarded_from_name',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'text',
    ];

    /**
     * @return HasMany<ChatMessageReaction, $this>
     */
    public function reactions(): HasMany
    {
        $reactions = $this->hasMany(ChatMessageReaction::class, 'message_id');
        $reactions->getQuery()->getQuery()->orderBy('id');

        return $reactions;
    }

    /**
     * @return HasMany<ChatAttachment, $this>
     */
    public function attachments(): HasMany
    {
        $attachments = $this->hasMany(ChatAttachment::class, 'message_id');
        $attachments->getQuery()->getQuery()->orderBy('id');

        return $attachments;
    }

    /**
     * @return BelongsTo<Call, $this>
     */
    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ChatMessageTypeEnum::class,
        ];
    }
}
