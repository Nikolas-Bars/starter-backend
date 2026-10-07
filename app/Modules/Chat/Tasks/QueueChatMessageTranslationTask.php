<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Enums\ChatMessageTypeEnum;
use App\Modules\Chat\Jobs\TranslateChatMessageJob;
use App\Modules\Chat\Models\ChatMessage;
use App\Services\Translation\TranslatorFactory;
use App\Tasks\BaseTask;

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
     * Ставит перевод текста сообщения в очередь; без ключа нейросети перевод выключен
     */
    public function run(ChatMessage $message): void
    {
        if ($message->type !== ChatMessageTypeEnum::Text || $message->body === '' || !$this->translatorFactory->enabled()) {
            return;
        }

        dispatch(new TranslateChatMessageJob($message->id))->onQueue(self::QUEUE);
    }
}
