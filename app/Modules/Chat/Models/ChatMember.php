<?php

declare(strict_types=1);

namespace App\Modules\Chat\Models;

use App\Modules\Chat\Database\Factories\ChatMemberFactory;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property      int         $id
 * @property      int         $chat_id
 * @property      int         $user_id
 * @property      int         $last_read_message_id Всё до этого сообщения включительно прочитано; 0 — ничего
 * @property-read Carbon|null $created_at
 * @property-read Carbon|null $updated_at
 * @property-read User        $user
 */
final class ChatMember extends Model
{
    /** @use HasFactory<ChatMemberFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'chat_id',
        'user_id',
        'last_read_message_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_read_message_id' => 'integer',
        ];
    }
}
