<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Actions;

use App\Actions\BaseAction;
use App\Modules\User\Models\User;
use App\Modules\Vpn\Exceptions\VpnNotAllowedException;
use App\Modules\Vpn\Models\VpnProfile;
use App\Modules\Vpn\Tasks\ListVpnProfilesTask;
use App\Modules\Vpn\Tasks\UserHasVpnAccessTask;
use Illuminate\Database\Eloquent\Collection;

final class GetVpnProfilesAction extends BaseAction
{
    public function __construct(
        private readonly UserHasVpnAccessTask $userHasVpnAccessTask,
        private readonly ListVpnProfilesTask  $listVpnProfilesTask,
    ) {
    }

    /**
     * @throws VpnNotAllowedException
     * @return Collection<int, VpnProfile>
     */
    public function run(User $user): Collection
    {
        if (!$this->userHasVpnAccessTask->run($user->id)) {
            throw new VpnNotAllowedException();
        }

        return $this->listVpnProfilesTask->run(onlyEnabled: true);
    }
}
