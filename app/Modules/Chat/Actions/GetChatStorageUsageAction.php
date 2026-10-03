<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\Tasks\GetChatStorageUsageTask;
use Illuminate\Support\Facades\Config;

final class GetChatStorageUsageAction extends BaseAction
{
    public function __construct(
        private readonly GetChatStorageUsageTask $getChatStorageUsageTask,
    ) {
    }

    /**
     * @return array{used: int, quota: int}
     */
    public function run(): array
    {
        return ['used' => $this->getChatStorageUsageTask->run(), 'quota' => Config::integer('attachments.quota_bytes')];
    }
}
