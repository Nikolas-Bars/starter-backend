<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Actions;

use App\Actions\BaseAction;
use App\Modules\CallLink\Models\CallLink;
use App\Modules\CallLink\Tasks\CreateCallLinkTask;
use App\Modules\CallLink\Tasks\FindCallLinkByUserTask;
use App\Modules\CallLink\Tasks\RotateCallLinkCodeTask;
use App\Modules\User\Models\User;

final class RotateCallLinkAction extends BaseAction
{
    public function __construct(
        private readonly FindCallLinkByUserTask $findCallLinkByUserTask,
        private readonly CreateCallLinkTask     $createCallLinkTask,
        private readonly RotateCallLinkCodeTask $rotateCallLinkCodeTask,
    ) {
    }

    /**
     * Новый код ссылки: старая ссылка сразу перестаёт работать. Гости, уже вошедшие
     * по ней, могут дозвониться владельцу, пока не истечёт их вход.
     */
    public function run(User $user): CallLink
    {
        $link = $this->findCallLinkByUserTask->run($user->id);

        return $link === null
            ? $this->createCallLinkTask->run($user->id)
            : $this->rotateCallLinkCodeTask->run($link);
    }
}
