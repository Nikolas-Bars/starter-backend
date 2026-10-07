<?php

declare(strict_types=1);

namespace App\Modules\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property      int         $id
 * @property      int         $message_id
 * @property      string      $locale     Язык перевода (из app.supported_locales)
 * @property      string      $body       Текст сообщения на этом языке
 * @property-read Carbon|null $created_at
 * @property-read Carbon|null $updated_at
 */
final class ChatMessageTranslation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'message_id',
        'locale',
        'body',
    ];
}
