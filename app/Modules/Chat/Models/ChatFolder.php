<?php

declare(strict_types=1);

namespace App\Modules\Chat\Models;

use App\Modules\Chat\Database\Factories\ChatFolderFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Папка чатов пользователя («Друзья», «Работа»): видна только владельцу
 *
 * @property      int                   $id
 * @property      int                   $user_id
 * @property      string                $name
 * @property-read Carbon|null           $created_at
 * @property-read Carbon|null           $updated_at
 * @property-read Collection<int, Chat> $chats
 * @property-read int|null              $unread_chats_count Чаты папки с непрочитанным у владельца (withCount)
 */
final class ChatFolder extends Model
{
    /** @use HasFactory<ChatFolderFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
    ];

    /**
     * @return BelongsToMany<Chat, $this>
     */
    public function chats(): BelongsToMany
    {
        return $this->belongsToMany(Chat::class, 'chat_folder_chats', 'folder_id', 'chat_id')->withTimestamps();
    }
}
