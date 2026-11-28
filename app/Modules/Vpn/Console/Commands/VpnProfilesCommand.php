<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Console\Commands;

use App\Modules\Vpn\Actions\ListVpnProfilesAction;
use App\Modules\Vpn\Models\VpnProfile;
use Illuminate\Console\Command;

final class VpnProfilesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'vpn:profiles';

    /**
     * @var string
     */
    protected $description = 'Профили VPN по порядку (ключ из ссылки не показывается)';

    public function handle(ListVpnProfilesAction $action): int
    {
        $profiles = $action->run();

        if ($profiles->isEmpty()) {
            $this->components->info('Профилей нет. Добавить: php artisan vpn:profile:add "Название"');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'Порядок', 'Название', 'Сервер', 'Транспорт', 'Включён'],
            $profiles->map(static function (VpnProfile $profile): array {
                $parts = \parse_url($profile->uri);
                \parse_str(\is_array($parts) ? ($parts['query'] ?? '') : '', $query);

                return [
                    $profile->id,
                    $profile->position,
                    $profile->name,
                    \is_array($parts) ? ($parts['host'] ?? '?') . ':' . ($parts['port'] ?? '?') : '?',
                    \sprintf('%s / %s', \is_string($query['type'] ?? null) ? $query['type'] : 'tcp', \is_string($query['security'] ?? null) ? $query['security'] : '—'),
                    $profile->is_enabled ? 'да' : 'нет',
                ];
            })->all(),
        );

        return self::SUCCESS;
    }
}
