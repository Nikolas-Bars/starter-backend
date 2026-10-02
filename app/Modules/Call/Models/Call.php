<?php

declare(strict_types=1);

namespace App\Modules\Call\Models;

use App\Modules\Call\Database\Factories\CallFactory;
use App\Modules\Call\Enums\CallStatusEnum;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property      int            $id
 * @property      int            $caller_id   Кто звонит
 * @property      int            $callee_id   Кому звонят
 * @property      CallStatusEnum $status
 * @property      Carbon         $started_at  Начало вызова
 * @property      Carbon|null    $answered_at Когда собеседник ответил
 * @property      Carbon|null    $ended_at    Когда звонок завершился
 * @property-read Carbon|null    $created_at
 * @property-read Carbon|null    $updated_at
 * @property-read User           $caller
 * @property-read User           $callee
 */
final class Call extends Model
{
    /** @use HasFactory<CallFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'caller_id',
        'callee_id',
        'status',
        'started_at',
        'answered_at',
        'ended_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function callee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'callee_id');
    }

    public function isParticipant(int $userId): bool
    {
        return $this->caller_id === $userId || $this->callee_id === $userId;
    }

    public function otherParticipantId(int $userId): int
    {
        return $this->caller_id === $userId ? $this->callee_id : $this->caller_id;
    }

    /**
     * Длительность разговора в секундах; для неотвеченных звонков — null.
     */
    public function durationSeconds(): ?int
    {
        if ($this->answered_at === null || $this->ended_at === null) {
            return null;
        }

        return (int)$this->answered_at->diffInSeconds($this->ended_at, true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status'      => CallStatusEnum::class,
            'started_at'  => 'datetime',
            'answered_at' => 'datetime',
            'ended_at'    => 'datetime',
        ];
    }
}
