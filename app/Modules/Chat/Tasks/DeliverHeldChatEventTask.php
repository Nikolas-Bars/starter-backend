<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\DTO\HeldChatEventDTO;
use App\Modules\Chat\Repositories\ChatMessageRepository;
use App\Tasks\BaseTask;
use Illuminate\Contracts\Cache\Repository as Cache;

final class DeliverHeldChatEventTask extends BaseTask
{
    private const string CACHE_PREFIX = 'chat:held-event:';

    public function __construct(
        private readonly ChatMessageRepository       $repository,
        private readonly PublishChatMessageEventTask $publishChatMessageEventTask,
        private readonly Cache                       $cache,
    ) {
    }

    /**
     * Отдаёт отложенное событие тем, кто ждал перевода, с сообщением в том виде, что сейчас в базе.
     * Ровно один раз: его отдаёт или перевод, или таймер — кто раньше. Сообщение удалили — отдавать нечего.
     *
     * @return bool false — событие уже отдали раньше
     */
    public function run(HeldChatEventDTO $held, int $messageId): bool
    {
        // Повторы перевода идут дольше минуты; час — с запасом
        if (!$this->cache->add(self::CACHE_PREFIX . $held->token, true, 3600)) {
            return false;
        }

        $message = $this->repository->findForResource($messageId);

        if ($message !== null) {
            $this->publishChatMessageEventTask->run($held->user_ids, $held->event, $message);
        }

        return true;
    }
}
