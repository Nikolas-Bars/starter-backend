<?php

declare(strict_types=1);

namespace App\Modules\Call\WebSockets;

/**
 * Разбор и сборка кадров WebSocket (RFC 6455, 5.2):
 *
 *   байт 0: FIN | RSV1-3 | opcode(4 бита)
 *   байт 1: MASK | длина(7 бит): 0–125 — сама длина, 126 — далее 16 бит, 127 — далее 64 бита
 *   [ключ маски, 4 байта] — есть только у кадров от клиента
 *   полезная нагрузка
 */
final readonly class FrameCodec
{
    public function __construct(
        private int $maxPayloadSize = 256 * 1024,
    ) {
    }

    public function maxPayloadSize(): int
    {
        return $this->maxPayloadSize;
    }

    /**
     * Достаёт из начала буфера один кадр и укорачивает буфер на прочитанные байты.
     *
     * @param bool $expectMasked Клиент обязан маскировать кадры, сервер — нет
     *
     *
     * @throws ProtocolException
     * @return Frame|null null — кадр пришёл не целиком, нужно дочитать сокет
     */
    public function decode(string &$buffer, bool $expectMasked = true): ?Frame
    {
        $available = \strlen($buffer);

        if ($available < 2) {
            return null;
        }

        $first  = \ord($buffer[0]);
        $second = \ord($buffer[1]);

        if (($first & 0x70) !== 0) {
            throw new ProtocolException('Reserved bits must be zero', ProtocolException::PROTOCOL_ERROR);
        }

        $fin    = ($first & 0x80) !== 0;
        $opcode = $first & 0x0F;
        $masked = ($second & 0x80) !== 0;
        $length = $second & 0x7F;
        $offset = 2;

        if (!Frame::isKnownOpcode($opcode)) {
            throw new ProtocolException('Unknown opcode', ProtocolException::PROTOCOL_ERROR);
        }

        if ($masked !== $expectMasked) {
            throw new ProtocolException($expectMasked ? 'Client frames must be masked' : 'Server frames must not be masked', ProtocolException::PROTOCOL_ERROR);
        }

        if ($length === 126) {
            if ($available < 4) {
                return null;
            }

            $length = $this->unpackLength('n', \substr($buffer, 2, 2));
            $offset = 4;
        } elseif ($length === 127) {
            if ($available < 10) {
                return null;
            }

            $length = $this->unpackLength('J', \substr($buffer, 2, 8));
            $offset = 10;
        }

        if (($opcode & 0x8) !== 0 && ($length > 125 || !$fin)) {
            throw new ProtocolException('Control frames must be short and unfragmented', ProtocolException::PROTOCOL_ERROR);
        }

        if ($length > $this->maxPayloadSize) {
            throw new ProtocolException('Message too big', ProtocolException::MESSAGE_TOO_BIG);
        }

        $maskKey = '';

        if ($masked) {
            if ($available < $offset + 4) {
                return null;
            }

            $maskKey = \substr($buffer, $offset, 4);
            $offset += 4;
        }

        if ($available < $offset + $length) {
            return null;
        }

        $payload = \substr($buffer, $offset, $length);
        $buffer  = \substr($buffer, $offset + $length);

        return new Frame($opcode, $masked ? $this->applyMask($payload, $maskKey) : $payload, $fin);
    }

    /**
     * Кадр целиком, без фрагментации.
     *
     * @param string|null $maskKey 4 байта маски — нужны только для кадров от клиента (в тестах)
     */
    public function encode(int $opcode, string $payload = '', ?string $maskKey = null): string
    {
        $length  = \strlen($payload);
        $maskBit = $maskKey === null ? 0 : 0x80;
        $header  = \chr(0x80 | $opcode);

        if ($length <= 125) {
            $header .= \chr($maskBit | $length);
        } elseif ($length <= 0xFFFF) {
            $header .= \chr($maskBit | 126) . \pack('n', $length);
        } else {
            $header .= \chr($maskBit | 127) . \pack('J', $length);
        }

        if ($maskKey === null) {
            return $header . $payload;
        }

        return $header . $maskKey . $this->applyMask($payload, $maskKey);
    }

    public function encodeClose(int $code, string $reason = ''): string
    {
        return $this->encode(Frame::CLOSE, \pack('n', $code) . \substr($reason, 0, 123));
    }

    /**
     * XOR с ключом маски: одна операция и для маскирования, и для снятия маски.
     */
    private function applyMask(string $payload, string $maskKey): string
    {
        $length = \strlen($payload);

        if ($length === 0) {
            return '';
        }

        return $payload ^ \substr(\str_repeat($maskKey, \intdiv($length, 4) + 1), 0, $length);
    }

    /**
     * @throws ProtocolException
     */
    private function unpackLength(string $format, string $bytes): int
    {
        $unpacked = \unpack($format, $bytes);
        $length   = $unpacked === false ? null : ($unpacked[1] ?? null);

        // Старший бит 64-битной длины обязан быть нулём: в PHP такое число станет отрицательным
        if (!\is_int($length) || $length < 0) {
            throw new ProtocolException('Invalid payload length', ProtocolException::PROTOCOL_ERROR);
        }

        return $length;
    }
}
