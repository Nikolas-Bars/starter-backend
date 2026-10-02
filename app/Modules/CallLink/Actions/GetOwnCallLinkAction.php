<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Actions;

use App\Actions\BaseAction;
use App\Modules\CallLink\Models\CallLink;
use App\Modules\CallLink\Tasks\CreateCallLinkTask;
use App\Modules\CallLink\Tasks\FindCallLinkByUserTask;
use App\Modules\User\Models\User;

final class GetOwnCallLinkAction extends BaseAction
{
    public function __construct(
        private readonly FindCallLinkByUserTask $findCallLinkByUserTask,
        private readonly CreateCallLinkTask     $createCallLinkTask,
    ) {
    }

    /**
     * Ссылка появляется при первом запросе: заводить её заранее каждому пользователю не нужно.
     */
    public function run(User $user): CallLink
    {
        return $this->findCallLinkByUserTask->run($user->id) ?? $this->createCallLinkTask->run($user->id);
    }
}
