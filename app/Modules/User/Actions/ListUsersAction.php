<?php

declare(strict_types=1);

namespace App\Modules\User\Actions;

use App\Actions\BaseAction;
use App\Modules\User\DTO\ListUsersDTO;
use App\Modules\User\Models\User;
use App\Modules\User\Tasks\ListUsersTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListUsersAction extends BaseAction
{
    public function __construct(
        private readonly ListUsersTask $listUsersTask,
    ) {
    }

    /**
     * Сам пользователь в список не попадает: позвонить себе нельзя.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function run(User $user, ListUsersDTO $dto): LengthAwarePaginator
    {
        return $this->listUsersTask->run($user->id, $dto);
    }
}
