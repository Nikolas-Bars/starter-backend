<?php

declare(strict_types=1);

namespace App\Modules\Call\Tasks;

use App\Modules\Call\Enums\CallStatusEnum;
use App\Modules\Call\Models\Call;
use App\Modules\Call\Repositories\CallRepository;
use App\Tasks\BaseTask;
use Illuminate\Support\Facades\Date;

final class CreateCallTask extends BaseTask
{
    public function __construct(
        private readonly CallRepository $repository,
    ) {
    }

    public function run(int $callerId, int $calleeId, CallStatusEnum $status): Call
    {
        return $this->repository->store($callerId, $calleeId, $status, Date::now());
    }
}
