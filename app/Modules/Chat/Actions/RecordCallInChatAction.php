<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Call\Enums\CallStatusEnum;
use App\Modules\Call\Models\Call;
use App\Modules\Chat\Http\Resources\ChatMessageResource;
use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatMember;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Tasks\AppendChatMessageTask;
use App\Modules\Chat\Tasks\CountUnreadMessagesTask;
use App\Modules\Chat\Tasks\CreateDirectChatTask;
use App\Modules\Chat\Tasks\FindChatMemberTask;
use App\Modules\Chat\Tasks\FindChatMessageByClientIdTask;
use App\Modules\Chat\Tasks\FindDirectChatTask;
use App\Modules\Chat\Tasks\PublishChatEventTask;
use App\Modules\Chat\Tasks\UpdateLastReadMessageTask;
use App\Modules\User\Tasks\FindUserByIdTask;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final class RecordCallInChatAction extends BaseAction
{
    public function __construct(
        private readonly FindUserByIdTask              $findUserByIdTask,
        private readonly FindDirectChatTask            $findDirectChatTask,
        private readonly CreateDirectChatTask          $createDirectChatTask,
        private readonly FindChatMemberTask            $findChatMemberTask,
        private readonly FindChatMessageByClientIdTask $findChatMessageByClientIdTask,
        private readonly AppendChatMessageTask         $appendChatMessageTask,
        private readonly UpdateLastReadMessageTask     $updateLastReadMessageTask,
        private readonly CountUnreadMessagesTask       $countUnreadMessagesTask,
        private readonly PublishChatEventTask          $publishChatEventTask,
    ) {
    }

    /**
     * Завершённый звонок появляется в личном чате участников служебным сообщением от звонившего
     * (чат создаётся, если его не было). Звонки с гостями по ссылке в чат не попадают.
     *
     * Собеседнику новым такое сообщение считается, только если он звонок пропустил: если он
     * ответил или сам отклонил вызов, а до этого всё прочитал, — остаётся прочитанным.
     *
     * @return ChatMessage|null null — записывать нечего или звонок уже записан
     */
    public function run(Call $call): ?ChatMessage
    {
        if ($call->status->isOngoing()) {
            return null;
        }

        $caller = $this->findUserByIdTask->run($call->caller_id);
        $callee = $this->findUserByIdTask->run($call->callee_id);

        if ($caller === null || $callee === null || $caller->isGuest() || $callee->isGuest()) {
            return null;
        }

        // client_id — UUID; выводим его из звонка, чтобы повторная запись не создала дубль
        $clientId = Uuid::uuid5(Uuid::NAMESPACE_URL, 'call:' . $call->id)->toString();

        if ($this->findChatMessageByClientIdTask->run($caller->id, $clientId) !== null) {
            return null;
        }

        $chat = $this->findDirectChatTask->run($caller->id, $callee->id) ?? $this->createChat($caller->id, $callee->id);

        try {
            $result = DB::transaction(function () use ($chat, $call, $clientId): array {
                $callerMember = $this->findChatMemberTask->run($chat->id, $call->caller_id);
                $calleeMember = $this->findChatMemberTask->run($chat->id, $call->callee_id);
                $previousId   = $chat->last_message_id ?? 0;

                $message   = $this->appendChatMessageTask->run($chat, $call->caller_id, $clientId, '', $call);
                $readMoves = [];

                // Своё сообщение автор уже «прочитал» — как при обычной отправке
                if ($callerMember !== null && $this->updateLastReadMessageTask->run($callerMember, $message->id)) {
                    $readMoves[] = $this->readState($callerMember);
                }

                if (
                    $calleeMember !== null
                    && $this->calleeSawCall($call)
                    && $calleeMember->last_read_message_id >= $previousId
                    && $this->updateLastReadMessageTask->run($calleeMember, $message->id)
                ) {
                    $readMoves[] = $this->readState($calleeMember);
                }

                return [$message, $readMoves];
            });
        } catch (UniqueConstraintViolationException) {
            // Тот же звонок записали параллельно
            return null;
        }

        [$message, $readMoves] = $result;

        $this->publish($call, $message, $readMoves);

        return $message;
    }

    /**
     * @return array<string, int> Данные события chat.read
     */
    private function readState(ChatMember $member): array
    {
        return [
            'chat_id'              => $member->chat_id,
            'user_id'              => $member->user_id,
            'last_read_message_id' => $member->last_read_message_id,
            'unread_count'         => $this->countUnreadMessagesTask->run($member),
        ];
    }

    private function calleeSawCall(Call $call): bool
    {
        return $call->status === CallStatusEnum::Ended || $call->status === CallStatusEnum::Rejected;
    }

    private function createChat(int $callerId, int $calleeId): Chat
    {
        try {
            return DB::transaction(fn(): Chat => $this->createDirectChatTask->run($callerId, $calleeId));
        } catch (UniqueConstraintViolationException $exception) {
            return $this->findDirectChatTask->run($callerId, $calleeId) ?? throw $exception;
        }
    }

    /**
     * @param list<array<string, int>> $readMoves У кого сдвинулась отметка прочитанного
     */
    private function publish(Call $call, ChatMessage $message, array $readMoves): void
    {
        $memberIds = [$call->caller_id, $call->callee_id];

        /** @var array<string, mixed> $payload */
        $payload = ChatMessageResource::make($message)->resolve();

        $this->publishChatEventTask->run($memberIds, SendChatMessageAction::EVENT, ['message' => $payload]);

        foreach ($readMoves as $readState) {
            $this->publishChatEventTask->run($memberIds, MarkChatReadAction::EVENT, $readState);
        }
    }
}
