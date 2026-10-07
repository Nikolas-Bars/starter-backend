<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Repositories\ChatMessageRepository;
use App\Tasks\BaseTask;
use Illuminate\Support\Facades\Config;

final class ListTranslationContextTask extends BaseTask
{
    /**
     * Длинное сообщение в контексте обрезается: ради смысла разговора хватает начала, а платим за каждый токен
     */
    public const int MAX_CONTEXT_LENGTH = 1000;

    public function __construct(
        private readonly ChatMessageRepository $repository,
    ) {
    }

    /**
     * Текстовые сообщения перед $message (translation.context_messages штук), от старых к новым
     *
     * @param array<int, string> $names user_id => имя
     *
     * @return list<array{author: string, text: string}>
     */
    public function run(ChatMessage $message, array $names): array
    {
        $messages = $this->repository->contextBefore($message->chat_id, $message->id, Config::integer('translation.context_messages'));

        return \array_map(
            static fn(ChatMessage $previous): array => [
                'author' => $previous->forwarded_from_name === null ? ($names[$previous->user_id] ?? '') : $previous->forwarded_from_name,
                'text'   => \mb_substr($previous->body, 0, self::MAX_CONTEXT_LENGTH),
            ],
            $messages,
        );
    }
}
