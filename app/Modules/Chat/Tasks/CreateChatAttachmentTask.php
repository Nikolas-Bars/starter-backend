<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Enums\ChatAttachmentKindEnum;
use App\Modules\Chat\Models\ChatAttachment;
use App\Modules\Chat\Repositories\ChatAttachmentRepository;
use App\Tasks\BaseTask;

final class CreateChatAttachmentTask extends BaseTask
{
    public function __construct(
        private readonly ChatAttachmentRepository $repository,
    ) {
    }

    public function run(int $userId, ChatAttachmentKindEnum $kind, string $originalName, string $mime, int $size, string $path): ChatAttachment
    {
        return $this->repository->store($userId, $kind, $originalName, $mime, $size, $path);
    }
}
