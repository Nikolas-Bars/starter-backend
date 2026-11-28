<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Console\Commands;

use App\Modules\Vpn\Actions\ListVpnAccessAction;
use App\Modules\Vpn\Actions\SetVpnAccessAction;
use App\Modules\Vpn\Models\VpnGrant;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class VpnAccessCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'vpn:access
        {user? : id или email; без него — список тех, кому VPN доступен}
        {--revoke : Забрать доступ}';

    /**
     * @var string
     */
    protected $description = 'Выдаёт или забирает доступ к VPN в приложении; без аргументов показывает, у кого он есть';

    public function handle(SetVpnAccessAction $setAccess, ListVpnAccessAction $listAccess): int
    {
        $user = $this->argument('user');

        if (!\is_string($user) || $user === '') {
            $grants = $listAccess->run();

            if ($grants->isEmpty()) {
                $this->components->info('VPN пока никому не выдан. Выдать: php artisan vpn:access email@example.com');

                return self::SUCCESS;
            }

            $this->table(['id', 'Имя', 'Email', 'Выдан'], $grants->map(static fn(VpnGrant $grant): array => [
                $grant->user->id,
                $grant->user->name,
                $grant->user->email,
                $grant->created_at?->format('Y-m-d H:i'),
            ])->all());

            return self::SUCCESS;
        }

        $revoke = $this->option('revoke') === true;

        try {
            ['user' => $found, 'changed' => $changed] = $setAccess->run($user, allowed: !$revoke);
        } catch (InvalidArgumentException $exception) {
            $this->components->error('Не получилось: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(\sprintf(
            '%s (%s): %s',
            $found->name,
            $found->email,
            match (true) {
                $revoke && $changed => 'VPN забран',
                $revoke             => 'VPN и так не был выдан',
                $changed            => 'VPN выдан',
                default             => 'VPN уже был выдан',
            },
        ));

        return self::SUCCESS;
    }
}
