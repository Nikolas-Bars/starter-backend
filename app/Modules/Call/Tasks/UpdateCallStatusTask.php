<?php

declare(strict_types=1);

namespace App\Modules\Call\Tasks;

use App\Modules\Call\Enums\CallStatusEnum;
use App\Modules\Call\Models\Call;
use App\Modules\Call\Repositories\CallRepository;
use App\Tasks\BaseTask;
use Illuminate\Support\Facades\Date;

final class UpdateCallStatusTask extends BaseTask
{
    public function __construct(
        private readonly CallRepository $repository,
    ) {
    }

    public function run(Call $call, CallStatusEnum $status): Call
    {
        $attributes = ['status' => $status];

        if ($status === CallStatusEnum::Active) {
            $attributes['answered_at'] = Date::now();
        }

        if (!$status->isOngoing()) {
            $attributes['ended_at'] = Date::now();
        }

        return $this->repository->update($call, $attributes);
    }
}
