<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Tasks;

use App\Modules\CallLink\Models\CallLink;
use App\Modules\CallLink\Repositories\CallLinkRepository;
use App\Tasks\BaseTask;

final class FindCallLinkByUserTask extends BaseTask
{
    public function __construct(
        private readonly CallLinkRepository $repository,
    ) {
    }

    public function run(int $userId): ?CallLink
    {
        return $this->repository->findByUserId($userId);
    }
}
