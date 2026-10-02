<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;

/**
 * Очередь событий от API к WebSocket-серверу: API работает в других процессах и не видит
 * открытые сокеты, поэтому кладёт событие сюда, а сервер забирает и рассылает адресатам.
 *
 * Событие — {"user_ids": [...], "message": {"type": "...", "data": {...}}}.
 */
final class RealtimeBus
{
    /**
     * @var list<array{user_ids: list<int>, message: array{type: string, data: array<string, mixed>}}>
     */
    private array $memory = [];

    /**
     * @param list<int>            $userIds
     * @param array<string, mixed> $data
     */
    public function publish(array $userIds, string $type, array $data): void
    {
        $event = ['user_ids' => \array_values(\array_unique($userIds)), 'message' => ['type' => $type, 'data' => $data]];

        if ($this->usesMemory()) {
            $this->memory[] = $event;

            return;
        }

        $this->redis()->command('rpush', [Config::string('realtime.redis.key'), \json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
    }

    /**
     * Забирает накопившиеся события (не больше $limit), каждое — ровно один раз.
     *
     * @return list<array{user_ids: list<int>, message: array{type: string, data: array<string, mixed>}}>
     */
    public function drain(int $limit): array
    {
        if ($this->usesMemory()) {
            $events       = \array_slice($this->memory, 0, $limit);
            $this->memory = \array_slice($this->memory, $limit);

            return $events;
        }

        $raw = $this->redis()->command('lpop', [Config::string('realtime.redis.key'), $limit]);

        if (!\is_array($raw)) {
            return [];
        }

        $events = [];

        foreach ($raw as $item) {
            $event = \is_string($item) ? $this->decode($item) : null;

            if ($event !== null) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * @return array{user_ids: list<int>, message: array{type: string, data: array<string, mixed>}}|null
     */
    private function decode(string $item): ?array
    {
        $event = \json_decode($item, true);

        if (!\is_array($event) || !\is_array($event['user_ids'] ?? null) || !\is_array($event['message'] ?? null)) {
            return null;
        }

        $message = $event['message'];

        if (!\is_string($message['type'] ?? null) || !\is_array($message['data'] ?? null)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $message['data'];

        return [
            'user_ids' => \array_values(\array_filter($event['user_ids'], \is_int(...))),
            'message'  => ['type' => $message['type'], 'data' => $data],
        ];
    }

    private function usesMemory(): bool
    {
        return Config::string('realtime.driver') === 'array';
    }

    private function redis(): Connection
    {
        return Redis::connection(Config::string('realtime.redis.connection'));
    }
}
