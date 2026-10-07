<?php

declare(strict_types=1);

namespace App\Modules\Chat\Models;

use App\Modules\Chat\Database\Factories\ChatFactory;
use App\Modules\Chat\Enums\ChatTypeEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property      int                         $id
 * @property      ChatTypeEnum                $type
 * @property      string|null                 $direct_key       Для личного чата: «меньший id:больший id» участников
 * @property      int|null                    $last_message_id  Последнее сообщение; null — переписки ещё нет
 * @property      string|null                 $translation_note Для переводчика: кто кем друг другу приходится
 * @property-read Carbon|null                 $created_at
 * @property-read Carbon|null                 $updated_at
 * @property-read Collection<int, ChatMember> $members
 * @property-read ChatMessage|null            $lastMessage
 * @property-read int|null                    $unread_count     Непрочитанные текущим пользователем (withCount)
 */
final class Chat extends Model
{
    /** @use HasFactory<ChatFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'direct_key',
        'last_message_id',
        'translation_note',
    ];

    /**
     * @return HasMany<ChatMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ChatMember::class);
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * Папки всех участников, куда добавлен чат: фильтровать по владельцу папки
     *
     * @return BelongsToMany<ChatFolder, $this>
     */
    public function folders(): BelongsToMany
    {
        return $this->belongsToMany(ChatFolder::class, 'chat_folder_chats', 'chat_id', 'folder_id')->withTimestamps();
    }

    /**
     * @return BelongsTo<ChatMessage, $this>
     */
    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'last_message_id');
    }

    public static function directKey(int $firstUserId, int $secondUserId): string
    {
        return \min($firstUserId, $secondUserId) . ':' . \max($firstUserId, $secondUserId);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ChatTypeEnum::class,
        ];
    }
}
