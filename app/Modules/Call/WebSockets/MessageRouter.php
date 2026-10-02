<?php

declare(strict_types=1);

namespace App\Modules\Call\WebSockets;

use App\Exceptions\ApiException;
use App\Modules\Call\Actions\AcceptCallAction;
use App\Modules\Call\Actions\EndCallAction;
use App\Modules\Call\Actions\InitiateCallAction;
use App\Modules\Call\Actions\MarkMissedCallsAction;
use App\Modules\Call\Actions\RejectCallAction;
use App\Modules\Call\Actions\ResumeCallAction;
use App\Modules\Call\Enums\CallStatusEnum;
use App\Modules\Call\Exceptions\InvalidCallStateException;
use App\Modules\Call\Exceptions\NotCallParticipantException;
use App\Modules\Call\Http\Resources\CallResource;
use App\Modules\Call\Models\Call;
use App\Modules\Chat\Actions\ListTypingRecipientsAction;
use App\Modules\Chat\Actions\RecordCallInChatAction;
use App\Modules\User\Models\User;
use App\Services\RealtimeBus;
use App\Services\Translator;
use Illuminate\Support\Facades\Config;
use JsonException;
use Throwable;

/**
 * Протокол сигнализации. Каждое сообщение — JSON вида {"type": "...", "data": {...}}.
 *
 * От клиента: ping, call.invite {callee_id}, call.accept|call.reject|call.hangup|call.resume {call_id},
 *             signal.offer|signal.answer|signal.ice {call_id, payload}, chat.typing {chat_id}.
 * От сервера: ready, pong, call.ringing, call.incoming, call.accepted, call.resumed {call},
 *             call.ended {call, reason}, signal.* {call_id, payload},
 *             presence.snapshot {user_ids}, presence.changed {user_id, online}, error {request, message},
 *             chat.typing {chat_id, user_id},
 *             а также события из RealtimeBus (chat.message, chat.read — см. модуль Chat).
 *
 * Звонок меняет состояние только через Actions; offer/answer/ICE в БД не пишутся,
 * а пересылаются собеседнику как есть. Завершённый звонок записывается в личный чат участников.
 *
 * Гость по ссылке для звонка (User::isGuest) видит в сети и может вызвать только владельца ссылки.
 *
 * Если во время разговора соединение участника оборвалось, разговор ждёт его
 * calls.websocket.resume_timeout секунд: клиент переподключается и присылает call.resume.
 */
final class MessageRouter
{
    private const SIGNAL_TYPES = [
        'signal.offer'  => true,
        'signal.answer' => true,
        'signal.ice'    => true,
    ];

    /**
     * Вызов приняли в другой вкладке того же пользователя
     */
    private const REASON_ANSWERED_ELSEWHERE = 'answered_elsewhere';

    public function __construct(
        private readonly ConnectionRegistry    $registry,
        private readonly InitiateCallAction    $initiateCallAction,
        private readonly AcceptCallAction      $acceptCallAction,
        private readonly RejectCallAction      $rejectCallAction,
        private readonly EndCallAction         $endCallAction,
        private readonly MarkMissedCallsAction $markMissedCallsAction,
        private readonly ResumeCallAction      $resumeCallAction,
        private readonly RecordCallInChatAction $recordCallInChatAction,
        private readonly ListTypingRecipientsAction $listTypingRecipientsAction,
        private readonly RealtimeBus           $realtimeBus,
    ) {
    }

    /**
     * Соединение прошло авторизацию: клиент получает «готово» и список тех, кто в сети,
     * а остальные узнают, что пользователь появился.
     */
    public function connected(Connection $connection): void
    {
        $user = $connection->user;

        if ($user === null) {
            return;
        }

        $connection->send(['type' => 'ready', 'data' => ['user_id' => $user->id]]);
        $connection->send(['type' => 'presence.snapshot', 'data' => ['user_ids' => $this->visibleOnlineUserIds($user)]]);

        if (\count($this->registry->forUser($user->id)) === 1) {
            $this->broadcastPresence($user, true, $connection);
        }
    }

    public function handle(Connection $connection, string $raw): void
    {
        $user = $connection->user;

        if ($user === null) {
            return;
        }

        $type = null;

        try {
            $message = \json_decode($raw, true, 32, JSON_THROW_ON_ERROR);

            if (!\is_array($message) || !\is_string($message['type'] ?? null)) {
                throw new InvalidMessageException();
            }

            $type = $message['type'];
            $data = \is_array($message['data'] ?? null) ? $message['data'] : [];

            $this->dispatch($connection, $user, $type, $data);
        } catch (ApiException $exception) {
            $this->sendError($connection, $type, $exception->getErrorMessage());
        } catch (InvalidMessageException|JsonException) {
            $this->sendError($connection, $type, Translator::get('exceptions.call.bad_message'));
        }
    }

    /**
     * Соединение закрылось. Вызов, на который ещё не ответили, завершается сразу;
     * идущий разговор ждёт, что участник переподключится.
     */
    public function disconnect(Connection $connection, ?float $now = null): void
    {
        $callId = $this->registry->remove($connection);
        $user   = $connection->user;

        if ($user === null) {
            return;
        }

        if (!$this->registry->isOnline($user->id)) {
            $this->broadcastPresence($user, false);
        }

        if ($callId === null) {
            return;
        }

        if ($this->registry->isAnswered($callId)) {
            $until = ($now ?? \microtime(true)) + Config::integer('calls.websocket.resume_timeout');
            $this->registry->suspend($callId, $user, $until);

            return;
        }

        $this->endFor($user, $callId);
    }

    /**
     * Раз в секунду: вызовы, на которые не ответили вовремя, становятся пропущенными,
     * а разговоры, куда участник не вернулся после обрыва, завершаются.
     */
    public function tick(int $ringTimeoutSeconds, ?float $now = null): void
    {
        foreach ($this->markMissedCallsAction->run($ringTimeoutSeconds) as $call) {
            $this->notifyEnded($call);
        }

        foreach ($this->registry->pullExpiredSuspensions($now ?? \microtime(true)) as $expired) {
            $this->endFor($expired['user'], $expired['callId']);
        }
    }

    /**
     * События, которые API положило в RealtimeBus (например, новое сообщение в чате),
     * уходят во все вкладки адресатов, что сейчас в сети.
     */
    public function flushRealtime(int $limit): void
    {
        foreach ($this->realtimeBus->drain($limit) as $event) {
            foreach ($event['user_ids'] as $userId) {
                $this->sendToUser($userId, $event['message']);
            }
        }
    }

    /**
     * @param array<mixed> $data
     *
     * @throws ApiException
     * @throws InvalidMessageException
     */
    private function dispatch(Connection $connection, User $user, string $type, array $data): void
    {
        if (isset(self::SIGNAL_TYPES[$type])) {
            $this->relaySignal($connection, $type, $this->intField($data, 'call_id'), $data['payload'] ?? null);

            return;
        }

        switch ($type) {
            case 'ping':
                $connection->send(['type' => 'pong']);
                break;
            case 'call.invite':
                $this->invite($connection, $user, $this->intField($data, 'callee_id'));
                break;
            case 'call.accept':
                $this->accept($connection, $user, $this->intField($data, 'call_id'));
                break;
            case 'call.reject':
                $this->notifyEnded($this->rejectCallAction->run($user, $this->intField($data, 'call_id')));
                break;
            case 'call.hangup':
                $this->notifyEnded($this->endCallAction->run($user, $this->intField($data, 'call_id')));
                break;
            case 'call.resume':
                $this->resume($connection, $user, $this->intField($data, 'call_id'));
                break;
            case 'chat.typing':
                $this->relayTyping($user, $this->intField($data, 'chat_id'));
                break;
            default:
                throw new InvalidMessageException();
        }
    }

    /**
     * @throws ApiException
     */
    private function invite(Connection $connection, User $caller, int $calleeId): void
    {
        $call    = $this->initiateCallAction->run($caller, $calleeId, $this->registry->isOnline($calleeId));
        $payload = ['call' => $this->present($call)];

        if ($call->status !== CallStatusEnum::Ringing) {
            $connection->send(['type' => 'call.ended', 'data' => $payload + ['reason' => $call->status->value]]);
            $this->recordInChat($call);

            return;
        }

        $this->registry->bindCall($call->id, $connection);

        $connection->send(['type' => 'call.ringing', 'data' => $payload]);
        $this->sendToUser($calleeId, ['type' => 'call.incoming', 'data' => $payload]);
    }

    /**
     * @throws ApiException
     */
    private function accept(Connection $connection, User $callee, int $callId): void
    {
        $call = $this->acceptCallAction->run($callee, $callId);
        $this->registry->bindCall($call->id, $connection);
        $this->registry->markAnswered($call->id);

        $accepted = ['type' => 'call.accepted', 'data' => ['call' => $this->present($call)]];
        $ended    = ['type' => 'call.ended', 'data' => ['call' => $this->present($call), 'reason' => self::REASON_ANSWERED_ELSEWHERE]];

        $this->registry->callConnection($call->id, $call->caller_id)?->send($accepted);

        foreach ($this->registry->forUser($callee->id) as $calleeConnection) {
            $calleeConnection->send($calleeConnection === $connection ? $accepted : $ended);
        }
    }

    /**
     * @throws ApiException
     */
    private function resume(Connection $connection, User $user, int $callId): void
    {
        $call = $this->resumeCallAction->run($user, $callId);

        if (!$this->registry->resume($call->id, $connection)) {
            throw new InvalidCallStateException();
        }

        $connection->send(['type' => 'call.resumed', 'data' => ['call' => $this->present($call)]]);
    }

    private function endFor(User $user, int $callId): void
    {
        try {
            $this->notifyEnded($this->endCallAction->run($user, $callId));
        } catch (ApiException) {
            $this->registry->releaseCall($callId);
        }
    }

    /**
     * Гость по ссылке и владелец ссылки видят в сети только друг друга (User::canContact).
     */
    private function broadcastPresence(User $subject, bool $online, ?Connection $except = null): void
    {
        $message = ['type' => 'presence.changed', 'data' => ['user_id' => $subject->id, 'online' => $online]];

        foreach ($this->registry->authenticated() as $connection) {
            if ($connection !== $except && $connection->user?->canContact($subject) === true) {
                $connection->send($message);
            }
        }
    }

    /**
     * @return list<int>
     */
    private function visibleOnlineUserIds(User $viewer): array
    {
        $userIds = [];

        foreach ($this->registry->authenticated() as $connection) {
            $online = $connection->user;

            if ($online !== null && $viewer->canContact($online)) {
                $userIds[$online->id] = $online->id;
            }
        }

        return \array_values($userIds);
    }

    /**
     * Offer/answer/ICE уходят только собеседнику по звонку, к которому привязано это соединение.
     *
     * @throws NotCallParticipantException
     * @throws InvalidMessageException
     */
    private function relaySignal(Connection $connection, string $type, int $callId, mixed $payload): void
    {
        if (!\is_array($payload)) {
            throw new InvalidMessageException();
        }

        if ($this->registry->callOf($connection) !== $callId) {
            throw new NotCallParticipantException();
        }

        $this->registry->peerOf($connection)?->send([
            'type' => $type,
            'data' => ['call_id' => $callId, 'payload' => $payload],
        ]);
    }

    /**
     * Сообщает о завершении всем, кого звонок касался: соединению звонящего и либо
     * вкладке, где собеседник ответил, либо (если ещё звонило) всем его вкладкам.
     */
    private function notifyEnded(Call $call): void
    {
        $message = ['type' => 'call.ended', 'data' => ['call' => $this->present($call), 'reason' => $call->status->value]];

        $calleeConnection = $this->registry->callConnection($call->id, $call->callee_id);

        $recipients   = $calleeConnection === null ? $this->registry->forUser($call->callee_id) : [$calleeConnection];
        $recipients[] = $this->registry->callConnection($call->id, $call->caller_id);

        foreach ($recipients as $recipient) {
            $recipient?->send($message);
        }

        $this->registry->releaseCall($call->id);
        $this->recordInChat($call);
    }

    /**
     * Сообщение о звонке в чате — дополнение: если записать не вышло, сигнализация продолжает работать
     */
    private function recordInChat(Call $call): void
    {
        try {
            $this->recordCallInChatAction->run($call);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * «Печатает» шлётся часто и ничего не меняет: чужой или несуществующий чат молча игнорируем
     */
    private function relayTyping(User $user, int $chatId): void
    {
        try {
            $recipients = $this->listTypingRecipientsAction->run($user, $chatId);
        } catch (ApiException) {
            return;
        }

        $message = ['type' => ListTypingRecipientsAction::EVENT, 'data' => ['chat_id' => $chatId, 'user_id' => $user->id]];

        foreach ($recipients as $userId) {
            $this->sendToUser($userId, $message);
        }
    }

    /**
     * @param array<string, mixed> $message
     */
    private function sendToUser(int $userId, array $message): void
    {
        foreach ($this->registry->forUser($userId) as $connection) {
            $connection->send($message);
        }
    }

    private function sendError(Connection $connection, ?string $request, string $message): void
    {
        $connection->send(['type' => 'error', 'data' => ['request' => $request, 'message' => $message]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Call $call): array
    {
        /** @var array<string, mixed> */
        return CallResource::make($call)->resolve();
    }

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidMessageException
     */
    private function intField(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (!\is_int($value)) {
            throw new InvalidMessageException();
        }

        return $value;
    }
}
