<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Actions;

use App\Actions\BaseAction;
use App\Modules\Vpn\Models\VpnGrant;
use App\Modules\Vpn\Tasks\ListVpnGrantsTask;
use Illuminate\Database\Eloquent\Collection;

final class ListVpnAccessAction extends BaseAction
{
    public function __construct(
        private readonly ListVpnGrantsTask $listVpnGrantsTask,
    ) {
    }

    /**
     * @return Collection<int, VpnGrant>
     */
    public function run(): Collection
    {
        return $this->listVpnGrantsTask->run();
    }
}
