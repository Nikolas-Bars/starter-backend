<?php

declare(strict_types=1);

namespace App\Modules\Chat\Console\Commands;

use App\Modules\Chat\Actions\PruneChatAttachmentsAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Number;

final class PruneAttachmentsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'attachments:prune
        {--before= : Удалить и отправленные файлы, загруженные раньше этой даты (ГГГГ-ММ-ДД)}';

    /**
     * @var string
     */
    protected $description = 'Удаляет файлы, которые не отправили за сутки, а с --before — и старые отправленные';

    public function handle(PruneChatAttachmentsAction $action): int
    {
        $before     = $this->option('before');
        $sentBefore = null;

        if (\is_string($before) && $before !== '') {
            if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $before) !== 1) {
                $this->components->error('Дата — в виде ГГГГ-ММ-ДД');

                return self::INVALID;
            }

            $sentBefore = Date::parse($before)->startOfDay();
        }

        $pruned = $action->run($sentBefore);

        $this->components->info(\sprintf('Удалено файлов: %d, освобождено %s', $pruned['count'], Number::fileSize($pruned['bytes'], 1)));

        return self::SUCCESS;
    }
}
