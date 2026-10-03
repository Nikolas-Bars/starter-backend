<?php

declare(strict_types=1);

namespace App\Modules\Chat\Jobs;

use App\Jobs\BaseJob;
use App\Modules\Chat\Actions\ProcessChatAttachmentAction;

/**
 * Сжатие фото и видео занимает до минут: идёт в отдельной очереди media, чтобы не задерживать push о звонках
 */
final class ProcessChatAttachmentJob extends BaseJob
{
    /**
     * Битый файл при повторе не починится, а ошибки обработки и так помечают вложение испорченным
     */
    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public readonly int $attachmentId,
    ) {
    }

    public function handle(ProcessChatAttachmentAction $action): void
    {
        $action->run($this->attachmentId);
    }
}
