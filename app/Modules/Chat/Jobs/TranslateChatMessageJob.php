<?php

declare(strict_types=1);

namespace App\Modules\Chat\Jobs;

use App\Jobs\BaseJob;
use App\Modules\Chat\Actions\TranslateChatMessageAction;

/**
 * В задаче только id: текст сообщения не попадает ни в Redis очереди, ни в failed_jobs
 */
final class TranslateChatMessageJob extends BaseJob
{
    /**
     * Провайдер иногда отвечает 429/5xx: повтор через паузу обычно проходит
     */
    public int $tries = 3;

    public int $timeout = 90;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public function __construct(
        public readonly int $messageId,
    ) {
    }

    public function handle(TranslateChatMessageAction $action): void
    {
        $action->run($this->messageId);
    }
}
