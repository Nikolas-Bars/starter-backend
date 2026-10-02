<?php

declare(strict_types=1);

namespace App\Modules\Push\Models;

use App\Modules\Push\Database\Factories\PushDeviceFactory;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Телефон, куда можно прислать push (Firebase Cloud Messaging).
 *
 * @property      int         $id
 * @property      int         $user_id
 * @property      int|null    $access_token_id Токен входа, с которым устройство зарегистрировано
 * @property      string      $token           Токен FCM
 * @property-read Carbon|null $created_at
 * @property-read Carbon|null $updated_at
 * @property-read User        $user
 */
final class PushDevice extends Model
{
    /** @use HasFactory<PushDeviceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'access_token_id',
        'token',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
