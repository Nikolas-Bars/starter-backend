<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Enums\ChatReactionEnum;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Repositories\ChatMessageReactionRepository;
use App\Modules\Chat\Repositories\ChatMessageRepository;
use App\Tasks\BaseTask;
use Illuminate\Database\UniqueConstraintViolationException;

final class SetMessageReactionTask extends BaseTask
{
    public function __construct(
        private readonly ChatMessageReactionRepository $reactionRepository,
        private readonly ChatMessageRepository         $messageRepository,
    ) {
    }

    /**
     * Ставит реакцию пользователя (заменяя прежнюю) или снимает её при $emoji = null.
     * Возвращает true, если что-то изменилось; реакции сообщения после этого перечитаны.
     * Вызывать внутри транзакции.
     */
    public function run(ChatMessage $message, int $userId, ?ChatReactionEnum $emoji): bool
    {
        $current = $this->reactionRepository->find($message->id, $userId);

        if ($current?->emoji === $emoji) {
            return false;
        }

        // Сменённая реакция встаёт в конец: удаляем прежнюю и создаём новую
        if ($current !== null) {
            $this->reactionRepository->delete($current);
        }

        if ($emoji !== null) {
            try {
                $this->reactionRepository->store($message->id, $userId, $emoji);
            } catch (UniqueConstraintViolationException) {
                // Параллельный запрос успел поставить реакцию — оставляем последнюю выбранную
                $raced = $this->reactionRepository->find($message->id, $userId);

                if ($raced !== null) {
                    $this->reactionRepository->updateEmoji($raced, $emoji);
                }
            }
        }

        $this->messageRepository->loadReactions($message);

        return true;
    }
}
