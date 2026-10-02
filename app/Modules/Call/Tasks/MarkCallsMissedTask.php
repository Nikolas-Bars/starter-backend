<?php

declare(strict_types=1);

namespace App\Modules\Call\Tasks;

use App\Modules\Call\Enums\CallStatusEnum;
use App\Modules\Call\Models\Call;
use App\Modules\Call\Repositories\CallRepository;
use App\Tasks\BaseTask;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

final class MarkCallsMissedTask extends BaseTask
{
    public function __construct(
        private readonly CallRepository $repository,
    ) {
    }

    /**
     * Обновляет звонки одним запросом и возвращает их в уже актуальном состоянии.
     *
     * @param Collection<int, Call> $calls
     *
     * @return Collection<int, Call>
     */
    public function run(Collection $calls): Collection
    {
        $endedAt = Date::now();

        /** @var list<int> $ids */
        $ids = $calls->modelKeys();
        $this->repository->finishMany($ids, CallStatusEnum::Missed, $endedAt);

        return $calls->each(static function (Call $call) use ($endedAt): void {
            $call->forceFill(['status' => CallStatusEnum::Missed, 'ended_at' => $endedAt])->syncOriginal();
        });
    }
}
