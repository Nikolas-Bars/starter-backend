<?php

declare(strict_types=1);

namespace App\Modules\Call\WebSockets;

use RuntimeException;

/**
 * Клиент нарушил протокол. Код — HTTP-статус, если ошибка случилась при рукопожатии,
 * и код закрытия WebSocket (RFC 6455, 7.4.1) — после него.
 */
final class ProtocolException extends RuntimeException
{
    public const PROTOCOL_ERROR = 1002;

    public const UNSUPPORTED_DATA = 1003;

    public const INVALID_PAYLOAD = 1007;

    public const MESSAGE_TOO_BIG = 1009;
}
