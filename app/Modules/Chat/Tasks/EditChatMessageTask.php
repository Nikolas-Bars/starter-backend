<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Repositories\ChatMessageRepository;
use App\Tasks\BaseTask;

final class EditChatMessageTask extends BaseTask
{
    public function __construct(
        private readonly ChatMessageRepository $repository,
    ) {
    }

    /**
     * Новый текст и отметка «изменено» (тот же текст ничего не меняет); сообщение возвращается
     * с реакциями и вложениями для ресурса
     */
    public function run(ChatMessage $message, string $body): ChatMessage
    {
        if ($message->body !== $body) {
            $this->repository->updateBody($message, $body);
        }

        return $this->repository->loadForResource($message);
    }
}
