<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Repositories\ChatMessageRepository;
use App\Tasks\BaseTask;

final class SaveChatMessageTranslationsTask extends BaseTask
{
    public function __construct(
        private readonly ChatMessageRepository $repository,
    ) {
    }

    /**
     * Язык оригинала и переводы; сообщение возвращается со всеми своими переводами
     *
     * @param array<string, string> $translations locale => текст
     */
    public function run(ChatMessage $message, string $bodyLocale, array $translations): ChatMessage
    {
        return $this->repository->saveTranslations($message, $bodyLocale, $translations);
    }
}
