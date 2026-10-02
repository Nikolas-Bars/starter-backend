<?php

declare(strict_types=1);

namespace App\Modules\Call\WebSockets;

use App\Modules\User\Models\User;

/**
 * Состояние одного клиента: буферы ввода/вывода, сборка фрагментированных сообщений, пользователь.
 * Про сокет ничего не знает — сокетами владеет Server, поэтому соединение легко проверять в тестах.
 */
final class Connection
{
    public ?User $user = null;

    /**
     * Рукопожатие пройдено, дальше идут кадры
     */
    public bool $upgraded = false;

    /**
     * Соединение закрывается: после отправки буфера сокет будет закрыт
     */
    public bool $closing = false;

    /**
     * Принятые, но ещё не разобранные байты
     */
    public string $inbound = '';

    /**
     * Мобильное приложение свёрнуто (client.state): вызов ему дублируется push-уведомлением
     */
    public bool $background = false;

    public float $lastActivityAt;

    public float $lastPingAt;

    private string $outbound = '';

    private ?int $messageOpcode = null;

    private string $messagePayload = '';

    public function __construct(
        public readonly int   $id,
        private readonly FrameCodec $codec,
        public readonly float $connectedAt,
    ) {
        $this->lastActivityAt = $connectedAt;
        $this->lastPingAt     = $connectedAt;
    }

    public function userId(): ?int
    {
        return $this->user?->id;
    }

    /**
     * @param array<string, mixed> $message
     */
    public function send(array $message): void
    {
        $this->sendFrame(Frame::TEXT, \json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function sendFrame(int $opcode, string $payload = ''): void
    {
        $this->queueRaw($this->codec->encode($opcode, $payload));
    }

    public function queueRaw(string $bytes): void
    {
        if (!$this->closing) {
            $this->outbound .= $bytes;
        }
    }

    public function close(int $code = 1000, string $reason = ''): void
    {
        if ($this->closing) {
            return;
        }

        if ($this->upgraded) {
            $this->queueRaw($this->codec->encodeClose($code, $reason));
        }

        $this->closing = true;
    }

    public function outbound(): string
    {
        return $this->outbound;
    }

    public function hasOutbound(): bool
    {
        return $this->outbound !== '';
    }

    public function consumeOutbound(int $bytes): void
    {
        $this->outbound = \substr($this->outbound, $bytes);
    }

    /**
     * Склеивает фрагменты сообщения (RFC 6455, 5.4).
     *
     *
     * @throws ProtocolException
     * @return Frame|null Сообщение целиком; null — ждём следующие фрагменты
     */
    public function assemble(Frame $frame): ?Frame
    {
        if ($frame->opcode === Frame::CONTINUATION) {
            if ($this->messageOpcode === null) {
                throw new ProtocolException('Unexpected continuation frame', ProtocolException::PROTOCOL_ERROR);
            }
        } elseif ($this->messageOpcode !== null) {
            throw new ProtocolException('Expected continuation frame', ProtocolException::PROTOCOL_ERROR);
        } else {
            $this->messageOpcode = $frame->opcode;
        }

        $this->messagePayload .= $frame->payload;

        if (\strlen($this->messagePayload) > $this->codec->maxPayloadSize()) {
            throw new ProtocolException('Message too big', ProtocolException::MESSAGE_TOO_BIG);
        }

        if (!$frame->fin) {
            return null;
        }

        $message = new Frame($this->messageOpcode, $this->messagePayload);

        $this->messageOpcode  = null;
        $this->messagePayload = '';

        return $message;
    }
}
