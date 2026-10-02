<?php

declare(strict_types=1);

namespace App\Modules\Call\Actions;

use App\Actions\BaseAction;
use App\Modules\Call\Models\Call;
use App\Modules\Call\Tasks\ListUserCallsTask;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListCallHistoryAction extends BaseAction
{
    public function __construct(
        private readonly ListUserCallsTask $listUserCallsTask,
    ) {
    }

    /**
     * @return LengthAwarePaginator<int, Call>
     */
    public function run(User $user, int $perPage): LengthAwarePaginator
    {
        return $this->listUserCallsTask->run($user->id, $perPage);
    }
}
