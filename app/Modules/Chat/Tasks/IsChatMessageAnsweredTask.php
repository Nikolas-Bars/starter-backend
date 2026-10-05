<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Repositories\ChatMessageRepository;
use App\Tasks\BaseTask;

final class IsChatMessageAnsweredTask extends BaseTask
{
    public function __construct(
        private readonly ChatMessageRepository $repository,
    ) {
    }

    /**
     * Ответили — значит, после сообщения в чате появилось что-то от другого участника (текст или звонок)
     */
    public function run(ChatMessage $message): bool
    {
        return $this->repository->hasOthersAfter($message->chat_id, $message->id, $message->user_id);
    }
}
