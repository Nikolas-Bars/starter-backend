<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Repositories\ChatAttachmentRepository;
use App\Tasks\BaseTask;

final class GetChatStorageUsageTask extends BaseTask
{
    public function __construct(
        private readonly ChatAttachmentRepository $repository,
    ) {
    }

    /**
     * Сколько байт занимают все вложения
     */
    public function run(): int
    {
        return $this->repository->totalSize();
    }
}
