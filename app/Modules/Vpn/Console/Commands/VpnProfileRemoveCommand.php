<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Console\Commands;

use App\Modules\Vpn\Actions\RemoveVpnProfileAction;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class VpnProfileRemoveCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'vpn:profile:remove {id : id из vpn:profiles}';

    /**
     * @var string
     */
    protected $description = 'Удаляет профиль VPN (чтобы временно спрятать — vpn:profile:edit --disable)';

    public function handle(RemoveVpnProfileAction $action): int
    {
        try {
            $profile = $action->run((int)$this->argument('id'));
        } catch (InvalidArgumentException $exception) {
            $this->components->error('Не получилось: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Профиль #{$profile->id} «{$profile->name}» удалён");

        return self::SUCCESS;
    }
}
