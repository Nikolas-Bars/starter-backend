<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Actions;

use App\Actions\BaseAction;
use App\Modules\CallLink\Exceptions\CallLinkNotFoundException;
use App\Modules\CallLink\Models\CallLink;
use App\Modules\CallLink\Tasks\FindCallLinkByCodeTask;

final class ShowCallLinkAction extends BaseAction
{
    public function __construct(
        private readonly FindCallLinkByCodeTask $findCallLinkByCodeTask,
    ) {
    }

    /**
     * @throws CallLinkNotFoundException
     */
    public function run(string $code): CallLink
    {
        return $this->findCallLinkByCodeTask->run($code) ?? throw new CallLinkNotFoundException();
    }
}
