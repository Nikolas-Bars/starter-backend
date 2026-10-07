<?php

declare(strict_types=1);

namespace App\Modules\User\Actions;

use App\Actions\BaseAction;
use App\Modules\User\Models\User;
use App\Modules\User\Tasks\UpdateUserLocaleTask;
use Illuminate\Support\Facades\App;

final class UpdateLocaleAction extends BaseAction
{
    public function __construct(
        private readonly UpdateUserLocaleTask $updateUserLocaleTask,
    ) {
    }

    /**
     * Ответ на этот запрос уже на новом языке: клиент переключает интерфейс по нему.
     */
    public function run(User $user, string $locale): User
    {
        $user = $this->updateUserLocaleTask->run($user, $locale);

        App::setLocale($locale);

        return $user;
    }
}
