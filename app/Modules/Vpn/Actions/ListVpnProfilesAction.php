<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Actions;

use App\Actions\BaseAction;
use App\Modules\Vpn\Models\VpnProfile;
use App\Modules\Vpn\Tasks\ListVpnProfilesTask;
use Illuminate\Database\Eloquent\Collection;

/**
 * Все профили, включая выключенные, — для artisan vpn:profiles.
 */
final class ListVpnProfilesAction extends BaseAction
{
    public function __construct(
        private readonly ListVpnProfilesTask $listVpnProfilesTask,
    ) {
    }

    /**
     * @return Collection<int, VpnProfile>
     */
    public function run(): Collection
    {
        return $this->listVpnProfilesTask->run(onlyEnabled: false);
    }
}
