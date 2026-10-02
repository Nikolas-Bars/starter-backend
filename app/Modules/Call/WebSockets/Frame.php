<?php

declare(strict_types=1);

namespace App\Modules\Call\WebSockets;

/**
 * Один кадр WebSocket (RFC 6455, раздел 5).
 */
final readonly class Frame
{
    public const CONTINUATION = 0x0;

    public const TEXT = 0x1;

    public const BINARY = 0x2;

    public const CLOSE = 0x8;

    public const PING = 0x9;

    public const PONG = 0xA;

    public function __construct(
        public int    $opcode,
        public string $payload = '',
        public bool   $fin = true,
    ) {
    }

    public static function isKnownOpcode(int $opcode): bool
    {
        return match ($opcode) {
            self::CONTINUATION, self::TEXT, self::BINARY, self::CLOSE, self::PING, self::PONG => true,
            default                                                                           => false,
        };
    }

    /**
     * Управляющие кадры (close/ping/pong) не фрагментируются и могут приходить между частями сообщения.
     */
    public function isControl(): bool
    {
        return ($this->opcode & 0x8) !== 0;
    }

    /**
     * Код закрытия из кадра CLOSE; null, если клиент его не указал.
     */
    public function closeCode(): ?int
    {
        if ($this->opcode !== self::CLOSE || \strlen($this->payload) < 2) {
            return null;
        }

        return (\ord($this->payload[0]) << 8) | \ord($this->payload[1]);
    }
}
