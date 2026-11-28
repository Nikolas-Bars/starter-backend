<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Models;

use App\Modules\User\Models\User;
use App\Modules\Vpn\Database\Factories\VpnGrantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Разрешение пользоваться VPN в приложении.
 *
 * @property      int         $id
 * @property      int         $user_id
 * @property-read Carbon|null $created_at Когда выдали
 * @property-read Carbon|null $updated_at
 * @property-read User        $user
 */
final class VpnGrant extends Model
{
    /** @use HasFactory<VpnGrantFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
