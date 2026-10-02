<?php

declare(strict_types=1);

namespace App\Modules\Call\Tests\Unit;

use App\Modules\Call\WebSockets\Connection;
use App\Modules\Call\WebSockets\Frame;
use App\Modules\Call\WebSockets\FrameCodec;
use App\Modules\Call\WebSockets\ProtocolException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrameCodecTest extends TestCase
{
    private const MASK = "\x37\xfa\x21\x3d";

    public function testEncodesShortServerFrameUnmasked(): void
    {
        self::assertSame("\x81\x02Hi", (new FrameCodec())->encode(Frame::TEXT, 'Hi'));
    }

    public function testDecodesMaskedClientFrameFromRfcExample(): void
    {
        // RFC 6455, 5.7: замаскированное "Hello"
        $buffer = "\x81\x85\x37\xfa\x21\x3d\x7f\x9f\x4d\x51\x58";

        $frame = (new FrameCodec())->decode($buffer);

        self::assertNotNull($frame);
        self::assertSame(Frame::TEXT, $frame->opcode);
        self::assertSame('Hello', $frame->payload);
        self::assertTrue($frame->fin);
        self::assertSame('', $buffer);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function payloadSizes(): iterable
    {
        yield '7 бит' => [125];
        yield '16 бит' => [126];
        yield '16 бит, максимум' => [65535];
        yield '64 бита' => [70000];
    }

    #[DataProvider('payloadSizes')]
    public function testRoundTripsEveryLengthEncoding(int $size): void
    {
        $codec   = new FrameCodec();
        $payload = \str_repeat('ж', \intdiv($size, 2)) . \str_repeat('a', $size % 2);
        $buffer  = $codec->encode(Frame::TEXT, $payload, self::MASK);

        $frame = $codec->decode($buffer);

        self::assertSame($payload, $frame?->payload);
    }

    public function testWaitsForTheRestOfTheFrame(): void
    {
        $codec  = new FrameCodec();
        $full   = $codec->encode(Frame::TEXT, \str_repeat('x', 300), self::MASK);
        $buffer = \substr($full, 0, 100);

        self::assertNull($codec->decode($buffer));
        self::assertSame(100, \strlen($buffer));

        $buffer .= \substr($full, 100);
        self::assertSame(300, \strlen((string)$codec->decode($buffer)?->payload));
    }

    public function testDecodesSeveralFramesFromOneBuffer(): void
    {
        $codec  = new FrameCodec();
        $buffer = $codec->encode(Frame::TEXT, 'one', self::MASK) . $codec->encode(Frame::PING, 'two', self::MASK);

        self::assertSame('one', $codec->decode($buffer)?->payload);
        self::assertSame(Frame::PING, $codec->decode($buffer)?->opcode);
        self::assertNull($codec->decode($buffer));
    }

    public function testRejectsUnmaskedClientFrame(): void
    {
        $buffer = (new FrameCodec())->encode(Frame::TEXT, 'Hi');

        $this->expectExceptionObject(new ProtocolException('Client frames must be masked', ProtocolException::PROTOCOL_ERROR));
        (new FrameCodec())->decode($buffer);
    }

    public function testRejectsTooBigMessageBeforeReadingIt(): void
    {
        $codec  = new FrameCodec(maxPayloadSize: 1000);
        $buffer = \substr($codec->encode(Frame::TEXT, \str_repeat('x', 2000), self::MASK), 0, 20);

        $this->expectExceptionCode(ProtocolException::MESSAGE_TOO_BIG);
        $codec->decode($buffer);
    }

    public function testRejectsFragmentedControlFrame(): void
    {
        $buffer = "\x09\x80" . self::MASK;

        $this->expectExceptionCode(ProtocolException::PROTOCOL_ERROR);
        (new FrameCodec())->decode($buffer);
    }

    public function testEncodesCloseCode(): void
    {
        $codec  = new FrameCodec();
        $buffer = $codec->encodeClose(4401, 'Unauthorized');

        $frame = $codec->decode($buffer, expectMasked: false);

        self::assertSame(4401, $frame?->closeCode());
        self::assertSame("\x11\x31Unauthorized", $frame->payload);
    }

    public function testConnectionAssemblesFragmentedMessage(): void
    {
        $connection = new Connection(1, new FrameCodec(), 0.0);

        self::assertNull($connection->assemble(new Frame(Frame::TEXT, '{"type":', false)));
        self::assertNull($connection->assemble(new Frame(Frame::CONTINUATION, '"ping"', false)));
        self::assertSame('{"type":"ping"}', $connection->assemble(new Frame(Frame::CONTINUATION, '}'))?->payload);
    }

    public function testConnectionRejectsContinuationWithoutStart(): void
    {
        $this->expectExceptionCode(ProtocolException::PROTOCOL_ERROR);
        (new Connection(1, new FrameCodec(), 0.0))->assemble(new Frame(Frame::CONTINUATION, 'x'));
    }
}
