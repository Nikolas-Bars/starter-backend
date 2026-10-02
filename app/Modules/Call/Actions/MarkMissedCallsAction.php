<?php

declare(strict_types=1);

namespace App\Modules\Call\Actions;

use App\Actions\BaseAction;
use App\Modules\Call\Models\Call;
use App\Modules\Call\Tasks\FindExpiredRingingCallsTask;
use App\Modules\Call\Tasks\MarkCallsMissedTask;
use Illuminate\Database\Eloquent\Collection;

final class MarkMissedCallsAction extends BaseAction
{
    public function __construct(
        private readonly FindExpiredRingingCallsTask $findExpiredRingingCallsTask,
        private readonly MarkCallsMissedTask         $markCallsMissedTask,
    ) {
    }

    /**
     * @return Collection<int, Call> Звонки, которые только что стали пропущенными
     */
    public function run(int $ringTimeoutSeconds): Collection
    {
        $calls = $this->findExpiredRingingCallsTask->run($ringTimeoutSeconds);

        if ($calls->isEmpty()) {
            return $calls;
        }

        return $this->markCallsMissedTask->run($calls);
    }
}
