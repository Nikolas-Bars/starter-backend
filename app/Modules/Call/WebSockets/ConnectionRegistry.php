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

        unset($this->callConnections[$callId]);
    }
}
