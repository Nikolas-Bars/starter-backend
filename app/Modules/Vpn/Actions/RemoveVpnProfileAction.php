<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Actions;

use App\Actions\BaseAction;
use App\Modules\Vpn\Models\VpnProfile;
use App\Modules\Vpn\Tasks\DeleteVpnProfileTask;
use App\Modules\Vpn\Tasks\FindVpnProfileTask;
use InvalidArgumentException;

final class RemoveVpnProfileAction extends BaseAction
{
    public function __construct(
        private readonly FindVpnProfileTask   $findVpnProfileTask,
        private readonly DeleteVpnProfileTask $deleteVpnProfileTask,
    ) {
    }

    /**
     * @throws InvalidArgumentException Профиля нет
     */
    public function run(int $id): VpnProfile
    {
        $profile = $this->findVpnProfileTask->run($id) ?? throw new InvalidArgumentException("профиля #{$id} нет");
        $this->deleteVpnProfileTask->run($profile);

        return $profile;
    }
}
