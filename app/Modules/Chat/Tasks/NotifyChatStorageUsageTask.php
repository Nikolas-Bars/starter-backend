<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Repositories\ChatAttachmentRepository;
use App\Services\TelegramNotifier;
use App\Tasks\BaseTask;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Number;

final class NotifyChatStorageUsageTask extends BaseTask
{
    private const string SENT_KEY = 'attachments:storage-alert-sent';

    public function __construct(
        private readonly ChatAttachmentRepository $repository,
        private readonly TelegramNotifier $telegram,
        private readonly Cache $cache,
    ) {
    }

    /**
     * Пишет администратору в Telegram, когда хранилище заполнено на 90%. Один раз: следующее
     * уведомление — только после того, как место освободят ниже 85%.
     */
    public function run(): void
    {
        $quota = Config::integer('attachments.quota_bytes');
        $used  = $this->repository->totalSize();
        $ratio = $quota > 0 ? $used / $quota : 1.0;

        if ($ratio < Config::float('attachments.rearm_ratio')) {
            $this->cache->forget(self::SENT_KEY);

            return;
        }

        if ($ratio < Config::float('attachments.notify_ratio') || !$this->cache->add(self::SENT_KEY, true)) {
            return;
        }

        $sent = $this->telegram->send(\sprintf(
            "%s: файлы чатов заняли %d%% хранилища (%s из %s). При 100%% новые файлы перестанут приниматься.\n\n"
                . "Сколько занято: php artisan attachments:usage\n"
                . 'Освободить место: php artisan attachments:prune --before=ГГГГ-ММ-ДД',
            (string)\parse_url(Config::string('app.url'), \PHP_URL_HOST),
            (int)\floor($ratio * 100),
            Number::fileSize($used, 1),
            Number::fileSize($quota, 1),
        ));

        // Не дошло — попробуем при следующей загрузке
        if (!$sent) {
            $this->cache->forget(self::SENT_KEY);
        }
    }
}
