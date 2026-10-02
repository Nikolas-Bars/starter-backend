<?php

declare(strict_types=1);

namespace App\Modules\Call\WebSockets;

/**
 * Рукопожатие WebSocket (RFC 6455, 4.2): клиент присылает обычный HTTP GET с заголовками
 * Upgrade: websocket и Sec-WebSocket-Key, сервер отвечает 101 и Sec-WebSocket-Accept.
 */
final class Handshake
{
    private const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    private const MAX_REQUEST_SIZE = 8192;

    private const STATUS_TEXTS = [
        400 => 'Bad Request',
        403 => 'Forbidden',
        426 => 'Upgrade Required',
    ];

    public static function acceptKey(string $key): string
    {
        return \base64_encode(\sha1($key . self::GUID, true));
    }

    /**
     * @throws ProtocolException Код исключения — HTTP-статус ответа
     * @return HandshakeRequest|null null — заголовки пришли не целиком
     */
    public function parse(string $buffer): ?HandshakeRequest
    {
        $end = \strpos($buffer, "\r\n\r\n");

        if ($end === false) {
            if (\strlen($buffer) > self::MAX_REQUEST_SIZE) {
                throw new ProtocolException('Request headers too large', 400);
            }

            return null;
        }

        $lines = \explode("\r\n", \substr($buffer, 0, $end));

        if (\preg_match('#^GET (\S+) HTTP/1\.1$#', \array_shift($lines), $matches) !== 1) {
            throw new ProtocolException('Expected GET request', 400);
        }

        $headers = $this->parseHeaders($lines);

        if (!\str_contains(\strtolower($headers['upgrade'] ?? ''), 'websocket')
            || !\str_contains(\strtolower($headers['connection'] ?? ''), 'upgrade')) {
            throw new ProtocolException('Not a WebSocket upgrade request', 400);
        }

        if (($headers['sec-websocket-version'] ?? '') !== '13') {
            throw new ProtocolException('Unsupported WebSocket version', 426);
        }

        $key = \base64_decode($headers['sec-websocket-key'] ?? '', true);

        if ($key === false || \strlen($key) !== 16) {
            throw new ProtocolException('Invalid Sec-WebSocket-Key', 400);
        }

        $target = $matches[1];
        \parse_str((string)\parse_url($target, PHP_URL_QUERY), $rawQuery);

        $query = [];

        foreach ($rawQuery as $name => $value) {
            if (\is_string($value)) {
                $query[(string)$name] = $value;
            }
        }

        return new HandshakeRequest(
            path: (string)\parse_url($target, PHP_URL_PATH),
            headers: $headers,
            query: $query,
            length: $end + 4,
        );
    }

    public function accept(HandshakeRequest $request): string
    {
        return "HTTP/1.1 101 Switching Protocols\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . 'Sec-WebSocket-Accept: ' . self::acceptKey((string)$request->header('sec-websocket-key')) . "\r\n\r\n";
    }

    public function reject(int $status, string $reason): string
    {
        return \sprintf("HTTP/1.1 %d %s\r\n", $status, self::STATUS_TEXTS[$status] ?? 'Error')
            . "Content-Type: text/plain; charset=utf-8\r\n"
            . 'Content-Length: ' . \strlen($reason) . "\r\n"
            . "Connection: close\r\n\r\n"
            . $reason;
    }

    /**
     * @param list<string> $lines
     *
     *
     * @throws ProtocolException
     * @return array<string, string>
     */
    private function parseHeaders(array $lines): array
    {
        $headers = [];

        foreach ($lines as $line) {
            $parts = \explode(':', $line, 2);

            if (\count($parts) !== 2 || \trim($parts[0]) === '') {
                throw new ProtocolException('Malformed header line', 400);
            }

            $headers[\strtolower(\trim($parts[0]))] = \trim($parts[1]);
        }

        return $headers;
    }
}
