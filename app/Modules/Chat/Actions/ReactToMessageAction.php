<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\Enums\ChatReactionEnum;
use App\Modules\Chat\Exceptions\ChatMessageNotFoundException;
use App\Modules\Chat\Exceptions\ChatNotFoundException;
use App\Modules\Chat\Http\Resources\ChatMessageResource;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Tasks\FindChatMemberTask;
use App\Modules\Chat\Tasks\FindChatMessageTask;
use App\Modules\Chat\Tasks\ListChatMemberIdsTask;
use App\Modules\Chat\Tasks\PublishChatEventTask;
use App\Modules\Chat\Tasks\SetMessageReactionTask;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

final class ReactToMessageAction extends BaseAction
{
    public const EVENT = 'chat.reaction';

    public function __construct(
        private readonly FindChatMemberTask     $findChatMemberTask,
        private readonly FindChatMessageTask    $findChatMessageTask,
        private readonly SetMessageReactionTask $setMessageReactionTask,
        private readonly ListChatMemberIdsTask  $listChatMemberIdsTask,
        private readonly PublishChatEventTask   $publishChatEventTask,
    ) {
    }

    /**
     * Ставит реакцию на сообщение (заменяя прежнюю) или снимает её при $emoji = null.
     * Если реакции изменились, участники получают событие chat.reaction.
     *
     * @throws ChatNotFoundException
     * @throws ChatMessageNotFoundException
     */
    public function run(User $user, int $chatId, int $messageId, ?ChatReactionEnum $emoji): ChatMessage
    {
        if ($this->findChatMemberTask->run($chatId, $user->id) === null) {
            throw new ChatNotFoundException();
        }

        $message = $this->findChatMessageTask->run($chatId, $messageId) ?? throw new ChatMessageNotFoundException();

        $changed = DB::transaction(fn(): bool => $this->setMessageReactionTask->run($message, $user->id, $emoji));

        if ($changed) {
            /** @var array{reactions: list<mixed>} $payload */
            $payload = ChatMessageResource::make($message)->resolve();

            $this->publishChatEventTask->run($this->listChatMemberIdsTask->run($chatId), self::EVENT, [
                'chat_id'    => $chatId,
                'message_id' => $message->id,
                'reactions'  => $payload['reactions'],
            ]);
        }

        return $message;
    }
}
