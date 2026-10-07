<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\DTO\HeldChatEventDTO;
use App\Modules\Chat\Models\ChatMessage;
use App\Tasks\BaseTask;
use Illuminate\Support\Str;

final class NotifyChatMessageTask extends BaseTask
{
    public function __construct(
        private readonly ListChatParticipantsTask        $listChatParticipantsTask,
        private readonly PublishChatMessageEventTask     $publishChatMessageEventTask,
        private readonly QueueChatMessageTranslationTask $queueChatMessageTranslationTask,
    ) {
    }

    /**
     * Рассылает участникам новое или изменённое сообщение (chat.message, chat.message_updated) и ставит
     * текст на перевод. Собеседник с другим языком интерфейса получит событие, когда перевод готов, —
     * сразу переведённым, а не оригиналом, который потом сменится; не успел перевод за
     * translation.hold_seconds — получит оригинал, перевод придёт следом.
     * Публиковать после фиксации транзакции.
     */
    public function run(ChatMessage $message, string $event): void
    {
        $members = $this->listChatParticipantsTask->run($message->chat_id);
        $locales = [];

        foreach ($members as $member) {
            $locales[$member->user_id] = $member->user->locale;
        }

        $held = [];

        if (isset($locales[$message->user_id]) && $this->queueChatMessageTranslationTask->applies($message)) {
            foreach ($locales as $userId => $locale) {
                if ($locale !== $locales[$message->user_id]) {
                    $held[] = $userId;
                }
            }
        }

        $this->publishChatMessageEventTask->run(\array_values(\array_diff(\array_keys($locales), $held)), $event, $message);
        $this->queueChatMessageTranslationTask->run($message, $held === [] ? null : new HeldChatEventDTO($event, $held, (string)Str::uuid()));
    }
}
