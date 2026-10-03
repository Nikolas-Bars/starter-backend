<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatAttachment;
use App\Modules\Chat\Repositories\ChatAttachmentRepository;
use App\Tasks\BaseTask;

final class UpdateChatAttachmentTask extends BaseTask
{
    public function __construct(
        private readonly ChatAttachmentRepository $repository,
    ) {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function run(ChatAttachment $attachment, array $attributes): ChatAttachment
    {
        return $this->repository->update($attachment, $attributes);
    }
}
