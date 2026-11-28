<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Actions;

use App\Actions\BaseAction;
use App\Modules\Vpn\DTO\VpnProfileChangesDTO;
use App\Modules\Vpn\Models\VpnProfile;
use App\Modules\Vpn\Tasks\FindVpnProfileTask;
use App\Modules\Vpn\Tasks\UpdateVpnProfileTask;
use App\Modules\Vpn\Tasks\ValidateVpnUriTask;
use InvalidArgumentException;

final class UpdateVpnProfileAction extends BaseAction
{
    public function __construct(
        private readonly FindVpnProfileTask   $findVpnProfileTask,
        private readonly ValidateVpnUriTask   $validateVpnUriTask,
        private readonly UpdateVpnProfileTask $updateVpnProfileTask,
    ) {
    }

    /**
     * @throws InvalidArgumentException Профиля нет или новая ссылка не годится
     */
    public function run(int $id, VpnProfileChangesDTO $changes): VpnProfile
    {
        $profile = $this->findVpnProfileTask->run($id) ?? throw new InvalidArgumentException("профиля #{$id} нет");

        if ($changes->uri !== null) {
            $problem = $this->validateVpnUriTask->run($changes->uri);

            if ($problem !== null) {
                throw new InvalidArgumentException($problem);
            }
        }

        return $this->updateVpnProfileTask->run($profile, $changes);
    }
}
