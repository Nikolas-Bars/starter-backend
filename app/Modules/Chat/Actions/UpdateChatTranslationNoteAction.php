<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\Exceptions\ChatNotFoundException;
use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Tasks\FindChatForMemberTask;
use App\Modules\Chat\Tasks\ListChatMemberIdsTask;
use App\Modules\Chat\Tasks\PublishChatEventTask;
use App\Modules\Chat\Tasks\UpdateChatTranslationNoteTask;
use App\Modules\User\Models\User;

final class UpdateChatTranslationNoteAction extends BaseAction
{
    public const EVENT = 'chat.translation_note';

    public function __construct(
        private readonly FindChatForMemberTask         $findChatForMemberTask,
        private readonly UpdateChatTranslationNoteTask $updateChatTranslationNoteTask,
        private readonly ListChatMemberIdsTask         $listChatMemberIdsTask,
        private readonly PublishChatEventTask          $publishChatEventTask,
    ) {
    }

    /**
     * Заметка для переводчика общая на чат: менять её может любой участник, остальные получают
     * chat.translation_note. Уже переведённые сообщения не переводятся заново.
     *
     * @throws ChatNotFoundException
     */
    public function run(User $user, int $chatId, ?string $note): Chat
    {
        $chat = $this->findChatForMemberTask->run($chatId, $user->id) ?? throw new ChatNotFoundException();

        if ($this->updateChatTranslationNoteTask->run($chat, $note)) {
            $this->publishChatEventTask->run($this->listChatMemberIdsTask->run($chatId), self::EVENT, [
                'chat_id'          => $chatId,
                'translation_note' => $chat->translation_note,
            ]);
        }

        return $chat;
    }
}
