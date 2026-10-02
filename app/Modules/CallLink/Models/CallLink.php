<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Models;

use App\Modules\CallLink\Database\Factories\CallLinkFactory;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Личная ссылка для звонка: по ней гость без регистрации звонит владельцу.
 *
 * @property      int         $id
 * @property      int         $user_id    Владелец ссылки
 * @property      string      $code       Код в адресе ссылки
 * @property-read Carbon|null $created_at
 * @property-read Carbon|null $updated_at
 * @property-read User        $owner
 */
final class CallLink extends Model
{
    /** @use HasFactory<CallLinkFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'code',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
