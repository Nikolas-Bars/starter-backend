<?php

declare(strict_types=1);

namespace App\Modules\Call\WebSockets;

/**
 * Разобранный HTTP-запрос на открытие WebSocket.
 */
final readonly class HandshakeRequest
{
    /**
     * @param array<string, string> $headers Имена заголовков в нижнем регистре
     * @param array<string, string> $query   Только строковые параметры строки запроса
     * @param int                   $length  Сколько байт буфера занял запрос
     */
    public function __construct(
        public string $path,
        public array  $headers,
        public array  $query,
        public int    $length,
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[\strtolower($name)] ?? null;
    }

    public function query(string $name): ?string
    {
        return $this->query[$name] ?? null;
    }
}
