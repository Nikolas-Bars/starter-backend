<?php

declare(strict_types=1);

namespace App\Modules\Chat\Models;

use App\Modules\Chat\Enums\ChatReactionEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property      int              $id
 * @property      int              $message_id
 * @property      int              $user_id
 * @property      ChatReactionEnum $emoji
 * @property-read Carbon|null      $created_at
 * @property-read Carbon|null      $updated_at
 */
final class ChatMessageReaction extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'message_id',
        'user_id',
        'emoji',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'emoji' => ChatReactionEnum::class,
        ];
    }
}
