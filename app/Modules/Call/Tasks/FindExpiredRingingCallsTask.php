<?php

declare(strict_types=1);

namespace App\Modules\Call\Tasks;

use App\Modules\Call\Models\Call;
use App\Modules\Call\Repositories\CallRepository;
use App\Tasks\BaseTask;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

final class FindExpiredRingingCallsTask extends BaseTask
{
    public function __construct(
        private readonly CallRepository $repository,
    ) {
    }

    /**
     * @return Collection<int, Call>
     */
    public function run(int $ringTimeoutSeconds): Collection
    {
        return $this->repository->findRingingStartedBefore(Date::now()->subSeconds($ringTimeoutSeconds));
    }
}
