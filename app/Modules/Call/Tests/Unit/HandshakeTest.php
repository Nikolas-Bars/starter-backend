<?php

declare(strict_types=1);

namespace App\Modules\Call\Tests\Unit;

use App\Modules\Call\WebSockets\Handshake;
use App\Modules\Call\WebSockets\ProtocolException;
use PHPUnit\Framework\TestCase;

final class HandshakeTest extends TestCase
{
    private const KEY = 'dGhlIHNhbXBsZSBub25jZQ==';

    public function testComputesAcceptKeyFromRfcExample(): void
    {
        self::assertSame('s3pPLMBiTxaQ9kYGzzhZRbK+xOo=', Handshake::acceptKey(self::KEY));
    }

    public function testParsesUpgradeRequestWithToken(): void
    {
        $raw = $this->request() . "\x81\x80";

        $request = (new Handshake())->parse($raw);

        self::assertNotNull($request);
        self::assertSame('/', $request->path);
        self::assertSame('1|secret', $request->query('token'));
        self::assertSame('http://localhost:5190', $request->header('Origin'));
        self::assertSame(\strlen($raw) - 2, $request->length);
    }

    public function testWaitsForCompleteHeaders(): void
    {
        self::assertNull((new Handshake())->parse("GET / HTTP/1.1\r\nHost: localhost\r\n"));
    }

    public function testRejectsPlainHttpRequest(): void
    {
        $this->expectExceptionObject(new ProtocolException('Not a WebSocket upgrade request', 400));
        (new Handshake())->parse("GET / HTTP/1.1\r\nHost: localhost\r\n\r\n");
    }

    public function testRequiresVersion13(): void
    {
        $this->expectExceptionCode(426);
        (new Handshake())->parse($this->request(['Sec-WebSocket-Version' => '8']));
    }

    public function testRejectsInvalidKey(): void
    {
        $this->expectExceptionCode(400);
        (new Handshake())->parse($this->request(['Sec-WebSocket-Key' => 'short']));
    }

    public function testRejectsOversizedHeaders(): void
    {
        $this->expectExceptionCode(400);
        (new Handshake())->parse("GET / HTTP/1.1\r\nX: " . \str_repeat('a', 9000));
    }

    public function testBuildsSwitchingProtocolsResponse(): void
    {
        $handshake = new Handshake();
        $request   = $handshake->parse($this->request());

        self::assertNotNull($request);
        self::assertSame(
            "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n"
            . "Sec-WebSocket-Accept: s3pPLMBiTxaQ9kYGzzhZRbK+xOo=\r\n\r\n",
            $handshake->accept($request),
        );
    }

    /**
     * @param array<string, string> $override
     */
    private function request(array $override = []): string
    {
        $headers = $override + [
            'Host'                  => 'localhost:8091',
            'Upgrade'               => 'websocket',
            'Connection'            => 'keep-alive, Upgrade',
            'Sec-WebSocket-Key'     => self::KEY,
            'Sec-WebSocket-Version' => '13',
            'Origin'                => 'http://localhost:5190',
        ];

        $lines = ['GET /?token=1%7Csecret HTTP/1.1'];

        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return \implode("\r\n", $lines) . "\r\n\r\n";
    }
}
