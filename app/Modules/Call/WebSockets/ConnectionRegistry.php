<?php

declare(strict_types=1);

namespace App\Modules\Call\WebSockets;

use App\Modules\User\Models\User;

/**
 * Кто сейчас на связи. У пользователя может быть несколько вкладок (соединений):
 * входящий вызов звонит во всех, а разговор идёт только в той, где его начали или приняли.
 */
final class ConnectionRegistry
{
    /**
     * @var array<int, Connection> id соединения => соединение
     */
    private array $connections = [];

    /**
     * @var array<int, array<int, Connection>> id пользователя => id соединения => соединение
     */
    private array $byUser = [];

    /**
     * @var array<int, array<int, Connection>> id звонка => id пользователя => соединение, где идёт звонок
     */
    private array $callConnections = [];

    /**
     * @var array<int, int> id соединения => id звонка
     */
    private array $callByConnection = [];

    /**
     * Звонки, на которые ответили: при обрыве соединения разговор не завершается сразу
     *
     * @var array<int, true> id звонка => true
     */
    private array $answeredCalls = [];

    /**
     * Участники разговора, у которых оборвалось соединение: ждём, что они вернутся с call.resume
     *
     * @var array<int, array<int, array{user: User, until: float}>> id звонка => id пользователя => до когда ждём
     */
    private array $suspended = [];

    public function add(Connection $connection): void
    {
        $this->connections[$connection->id] = $connection;
    }

    public function authenticate(Connection $connection, User $user): void
    {
        $connection->user = $user;

        $this->byUser[$user->id][$connection->id] = $connection;
    }

    /**
     * @return int|null Звонок, к которому было привязано соединение
     */
    public function remove(Connection $connection): ?int
    {
        $callId = $this->callOf($connection);

        unset($this->connections[$connection->id], $this->callByConnection[$connection->id]);

        $userId = $connection->userId();

        if ($userId !== null) {
            unset($this->byUser[$userId][$connection->id]);

            if (($this->byUser[$userId] ?? []) === []) {
                unset($this->byUser[$userId]);
            }

            if ($callId !== null) {
                unset($this->callConnections[$callId][$userId]);
            }
        }

        return $callId;
    }

    /**
     * @return list<Connection>
     */
    public function all(): array
    {
        return \array_values($this->connections);
    }

    /**
     * @return list<Connection>
     */
    public function forUser(int $userId): array
    {
        return \array_values($this->byUser[$userId] ?? []);
    }

    public function isOnline(int $userId): bool
    {
        return isset($this->byUser[$userId]);
    }

    /**
     * @return list<int>
     */
    public function onlineUserIds(): array
    {
        return \array_keys($this->byUser);
    }

    /**
     * @return list<Connection> Соединения, прошедшие авторизацию
     */
    public function authenticated(): array
    {
        $connections = [];

        foreach ($this->byUser as $userConnections) {
            foreach ($userConnections as $connection) {
                $connections[] = $connection;
            }
        }

        return $connections;
    }

    public function markAnswered(int $callId): void
    {
        $this->answeredCalls[$callId] = true;
    }

    public function isAnswered(int $callId): bool
    {
        return isset($this->answeredCalls[$callId]);
    }

    public function suspend(int $callId, User $user, float $until): void
    {
        $this->suspended[$callId][$user->id] = ['user' => $user, 'until' => $until];
    }

    /**
     * Привязывает разговор к новому соединению пользователя: после обрыва (участник был в ожидании)
     * или когда сервер ещё не заметил, что старое соединение умерло.
     *
     * @return bool false — этот пользователь не участвует в разговоре на сервере
     */
    public function resume(int $callId, Connection $connection): bool
    {
        $userId = $connection->userId();

        if ($userId === null) {
            return false;
        }

        $previous = $this->callConnections[$callId][$userId] ?? null;

        if (!isset($this->suspended[$callId][$userId]) && $previous === null) {
            return false;
        }

        unset($this->suspended[$callId][$userId]);

        if (($this->suspended[$callId] ?? null) === []) {
            unset($this->suspended[$callId]);
        }

        if ($previous !== null) {
            unset($this->callByConnection[$previous->id]);
        }

        $this->bindCall($callId, $connection);

        return true;
    }

    /**
     * Снимает с ожидания тех, кто не вернулся вовремя.
     *
     * @return list<array{callId: int, user: User}>
     */
    public function pullExpiredSuspensions(float $now): array
    {
        $expired = [];

        foreach ($this->suspended as $callId => $users) {
            foreach ($users as $userId => $suspension) {
                if ($suspension['until'] <= $now) {
                    $expired[] = ['callId' => $callId, 'user' => $suspension['user']];
                    unset($this->suspended[$callId][$userId]);
                }
            }

            if ($this->suspended[$callId] === []) {
                unset($this->suspended[$callId]);
            }
        }

        return $expired;
    }

    public function bindCall(int $callId, Connection $connection): void
    {
        $userId = $connection->userId();

        if ($userId === null) {
            return;
        }

        $this->callConnections[$callId][$userId] = $connection;
        $this->callByConnection[$connection->id] = $callId;
    }

    public function callOf(Connection $connection): ?int
    {
        return $this->callByConnection[$connection->id] ?? null;
    }

    public function callConnection(int $callId, int $userId): ?Connection
    {
        return $this->callConnections[$callId][$userId] ?? null;
    }

    /**
     * Соединение собеседника в звонке, к которому привязано это соединение.
     */
    public function peerOf(Connection $connection): ?Connection
    {
        $callId = $this->callOf($connection);

        if ($callId === null) {
            return null;
        }

        foreach ($this->callConnections[$callId] ?? [] as $userId => $peer) {
            if ($userId !== $connection->userId()) {
                return $peer;
            }
        }

        return null;
    }

    public function releaseCall(int $callId): void
    {
        foreach ($this->callConnections[$callId] ?? [] as $connection) {
            unset($this->callByConnection[$connection->id]);
        }

        unset($this->callConnections[$callId], $this->answeredCalls[$callId], $this->suspended[$callId]);
    }
}
