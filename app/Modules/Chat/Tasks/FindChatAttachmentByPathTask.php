<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatAttachment;
use App\Modules\Chat\Repositories\ChatAttachmentRepository;
use App\Tasks\BaseTask;

final class FindChatAttachmentByPathTask extends BaseTask
{
    public function __construct(
        private readonly ChatAttachmentRepository $repository,
    ) {
    }

    /**
     * Вложение, которому принадлежит файл: сам файл или его превью
     */
    public function run(string $path): ?ChatAttachment
    {
        return $this->repository->findByPath($path);
    }
}
