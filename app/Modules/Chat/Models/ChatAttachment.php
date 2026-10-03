<?php

declare(strict_types=1);

namespace App\Modules\Chat\Models;

use App\Modules\Chat\Database\Factories\ChatAttachmentFactory;
use App\Modules\Chat\Enums\ChatAttachmentKindEnum;
use App\Modules\Chat\Enums\ChatAttachmentStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property      int                      $id
 * @property      int                      $user_id       Кто загрузил
 * @property      int|null                 $message_id    Пусто, пока сообщение с файлом не отправлено
 * @property      ChatAttachmentKindEnum   $kind
 * @property      ChatAttachmentStatusEnum $status
 * @property      string                   $original_name Имя файла у отправителя
 * @property      string                   $mime
 * @property      int                      $size          Байт на диске вместе с превью
 * @property      string                   $path          Путь на диске attachments; во время обработки — оригинал
 * @property      string|null              $thumb_path
 * @property      int|null                 $width
 * @property      int|null                 $height
 * @property      int|null                 $duration_ms
 * @property      list<int>|null           $waveform      Громкость голосового по отрезкам, 0–100
 * @property-read Carbon|null              $created_at
 * @property-read Carbon|null              $updated_at
 * @property-read ChatMessage|null         $message
 */
final class ChatAttachment extends Model
{
    /** @use HasFactory<ChatAttachmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'message_id',
        'kind',
        'status',
        'original_name',
        'mime',
        'size',
        'path',
        'thumb_path',
        'width',
        'height',
        'duration_ms',
        'waveform',
    ];

    /**
     * @return BelongsTo<ChatMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind'     => ChatAttachmentKindEnum::class,
            'status'   => ChatAttachmentStatusEnum::class,
            'waveform' => 'array',
        ];
    }
}
