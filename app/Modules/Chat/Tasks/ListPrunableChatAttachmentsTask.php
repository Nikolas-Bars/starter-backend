<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatAttachment;
use App\Modules\Chat\Repositories\ChatAttachmentRepository;
use App\Tasks\BaseTask;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;

final class ListPrunableChatAttachmentsTask extends BaseTask
{
    public function __construct(
        private readonly ChatAttachmentRepository $repository,
    ) {
    }

    /**
     * Не отправленные за сутки и, если указана дата, все загруженные раньше неё
     *
     * @return Collection<int, ChatAttachment>
     */
    public function run(?Carbon $sentBefore): Collection
    {
        $unsent = $this->repository->unsentBefore(Date::now()->subHours(Config::integer('attachments.orphan_ttl_hours')));

        return $sentBefore === null ? $unsent : $unsent->merge($this->repository->sentBefore($sentBefore));
    }
}
