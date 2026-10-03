<?php

declare(strict_types=1);

namespace App\Modules\Chat\Console\Commands;

use App\Modules\Chat\Actions\GetChatStorageUsageAction;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

final class ShowAttachmentsUsageCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'attachments:usage';

    /**
     * @var string
     */
    protected $description = 'Показывает, сколько места заняли файлы чатов';

    public function handle(GetChatStorageUsageAction $action): int
    {
        $usage = $action->run();

        $this->components->info(\sprintf(
            'Занято %s из %s (%d%%)',
            Number::fileSize($usage['used'], 1),
            Number::fileSize($usage['quota'], 1),
            $usage['quota'] > 0 ? (int)\floor($usage['used'] * 100 / $usage['quota']) : 100,
        ));

        return self::SUCCESS;
    }
}
