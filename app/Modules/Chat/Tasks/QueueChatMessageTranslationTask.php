<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\DTO\HeldChatEventDTO;
use App\Modules\Chat\Enums\ChatMessageTypeEnum;
use App\Modules\Chat\Jobs\DeliverHeldChatEventJob;
use App\Modules\Chat\Jobs\TranslateChatMessageJob;
use App\Modules\Chat\Models\ChatMessage;
use App\Services\Translation\TranslatorFactory;
use App\Tasks\BaseTask;
use Illuminate\Support\Facades\Config;

final class QueueChatMessageTranslationTask extends BaseTask
{
    /**
     * Свою очередь, чтобы ожидание ответа нейросети не задерживало push о звонках и сжатие видео
     */
    public const string QUEUE = 'translation';

    public function __construct(
        private readonly TranslatorFactory $translatorFactory,
    ) {
    }

    /**
     * Есть что переводить: текст и ключ нейросети (без ключа перевод выключен)
     */
    public function applies(ChatMessage $message): bool
    {
        return $message->type === ChatMessageTypeEnum::Text && $message->body !== '' && $this->translatorFactory->enabled();
    }

    /**
     * Ставит перевод текста сообщения в очередь. $held — событие, которое ждёт перевода: перевод отдаст его
     * сам, а если не успеет за translation.hold_seconds, событие с оригиналом отдаст таймер
     */
    public function run(ChatMessage $message, ?HeldChatEventDTO $held = null): void
    {
        if (!$this->applies($message)) {
            return;
        }

        dispatch(new TranslateChatMessageJob($message->id, $held))->onQueue(self::QUEUE);

        if ($held !== null) {
            dispatch(new DeliverHeldChatEventJob($message->id, $held))->delay(Config::integer('translation.hold_seconds'));
        }
    }
}
