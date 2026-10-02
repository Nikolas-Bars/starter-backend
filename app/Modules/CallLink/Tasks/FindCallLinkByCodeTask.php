<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Tasks;

use App\Modules\CallLink\Models\CallLink;
use App\Modules\CallLink\Repositories\CallLinkRepository;
use App\Tasks\BaseTask;

final class FindCallLinkByCodeTask extends BaseTask
{
    public function __construct(
        private readonly CallLinkRepository $repository,
    ) {
    }

    /**
     * Ссылка вместе с владельцем.
     */
    public function run(string $code): ?CallLink
    {
        return $this->repository->findByCodeWithOwner($code);
    }
}
