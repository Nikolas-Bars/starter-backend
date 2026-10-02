<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Services\RealtimeBus;
use App\Tasks\BaseTask;

final class NotifyChatFoldersChangedTask extends BaseTask
{
    public const EVENT = 'chat.folders';

    public function __construct(
        private readonly RealtimeBus $realtimeBus,
    ) {
    }

    /**
     * Другие вкладки владельца перечитывают папки (событие chat.folders без данных)
     */
    public function run(int $userId): void
    {
        $this->realtimeBus->publish([$userId], self::EVENT, []);
    }
}
