<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Actions;

use App\Actions\BaseAction;
use App\Modules\Vpn\DTO\VpnProfileDTO;
use App\Modules\Vpn\Models\VpnProfile;
use App\Modules\Vpn\Tasks\CreateVpnProfileTask;
use App\Modules\Vpn\Tasks\ValidateVpnUriTask;
use InvalidArgumentException;

final class AddVpnProfileAction extends BaseAction
{
    public function __construct(
        private readonly ValidateVpnUriTask   $validateVpnUriTask,
        private readonly CreateVpnProfileTask $createVpnProfileTask,
    ) {
    }

    /**
     * @throws InvalidArgumentException Ссылку приложение не поднимет
     */
    public function run(VpnProfileDTO $dto): VpnProfile
    {
        $problem = $this->validateVpnUriTask->run($dto->uri);

        if ($problem !== null) {
            throw new InvalidArgumentException($problem);
        }

        return $this->createVpnProfileTask->run($dto);
    }
}
