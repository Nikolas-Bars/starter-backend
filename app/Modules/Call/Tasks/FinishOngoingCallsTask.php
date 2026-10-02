<?php

declare(strict_types=1);

namespace App\Modules\Call\Tasks;

use App\Modules\Call\Repositories\CallRepository;
use App\Tasks\BaseTask;
use Illuminate\Support\Facades\Date;

final class FinishOngoingCallsTask extends BaseTask
{
    public function __construct(
        private readonly CallRepository $repository,
    ) {
    }

    /**
     * @return int Сколько звонков закрыто
     */
    public function run(): int
    {
        return $this->repository->finishAllOngoing(Date::now());
    }
}
