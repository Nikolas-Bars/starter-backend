<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\DTO\ChatMessagesPageDTO;
use App\Modules\Chat\DTO\ListChatMessagesDTO;
use App\Modules\Chat\Exceptions\ChatNotFoundException;
use App\Modules\Chat\Tasks\FindChatMemberTask;
use App\Modules\Chat\Tasks\ListChatMessagesTask;
use App\Modules\User\Models\User;

final class ListChatMessagesAction extends BaseAction
{
    public function __construct(
        private readonly FindChatMemberTask   $findChatMemberTask,
        private readonly ListChatMessagesTask $listChatMessagesTask,
    ) {
    }

    /**
     * Страница переписки: $dto->limit сообщений перед before_id, от старых к новым.
     *
     * @throws ChatNotFoundException
     */
    public function run(User $user, int $chatId, ListChatMessagesDTO $dto): ChatMessagesPageDTO
    {
        if ($this->findChatMemberTask->run($chatId, $user->id) === null) {
            throw new ChatNotFoundException();
        }

        // Берём на одно больше: так без отдельного запроса видно, есть ли что листать дальше
        $messages = $this->listChatMessagesTask->run($chatId, $dto->before_id, $dto->limit + 1);
        $hasMore  = $messages->count() > $dto->limit;

        return new ChatMessagesPageDTO(
            items: \array_values(\array_reverse($messages->take($dto->limit)->all())),
            has_more: $hasMore,
        );
    }
}
