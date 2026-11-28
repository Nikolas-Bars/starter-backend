<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Models;

use App\Modules\Vpn\Database\Factories\VpnProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Профиль VPN: сервер и способ подключения — ссылкой (vless://, trojan://, ss://…) или JSON исходящих Xray.
 * Приложение показывает включённые профили по position и перебирает их по очереди.
 *
 * @property      int         $id
 * @property      string      $name       Название в списке («Основной · TCP»)
 * @property      string      $uri        Ссылка или JSON исходящих Xray (зашифрован в БД)
 * @property      int         $position   Порядок: меньше — выше в списке
 * @property      bool        $is_enabled Выключенный профиль клиентам не отдаётся
 * @property-read Carbon|null $created_at
 * @property-read Carbon|null $updated_at
 */
final class VpnProfile extends Model
{
    /** @use HasFactory<VpnProfileFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'uri',
        'position',
        'is_enabled',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'uri',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'uri'        => 'encrypted',
            'position'   => 'integer',
            'is_enabled' => 'boolean',
        ];
    }
}
