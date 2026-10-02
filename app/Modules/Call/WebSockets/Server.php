<?php

declare(strict_types=1);

namespace App\Modules\Call\WebSockets;

use App\Modules\Call\Actions\AuthenticateWebSocketAction;
use App\Modules\Call\Actions\FinishStaleCallsAction;
use App\Services\Translator;
use Closure;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Socket;
use Throwable;

/**
 * WebSocket-сервер сигнализации: один процесс, неблокирующие сокеты и socket_select.
 * Медиа (видео и звук) через сервер не идут — только короткие JSON-сообщения.
 */
final class Server
{
    private const READ_CHUNK = 65536;

    private const HOUSEKEEPING_INTERVAL = 1.0;

    /**
     * Сколько секунд даём клиенту на рукопожатие
     */
    private const HANDSHAKE_TIMEOUT = 10.0;

    private const CLOSE_GOING_AWAY = 1001;

    private const CLOSE_UNAUTHORIZED = 4401;

    private ?Socket $listener = null;

    private bool $running = false;

    private int $nextConnectionId = 1;

    private float $lastHousekeepingAt = 0.0;

    /**
     * @var array<int, Socket> id соединения => сокет
     */
    private array $sockets = [];

    /**
     * @var array<int, Connection> spl_object_id(сокета) => соединение
     */
    private array $connectionsBySocket = [];

    /**
     * @var Closure(string): void
     */
    private Closure $log;

    public function __construct(
        private readonly ConnectionRegistry          $registry,
        private readonly MessageRouter               $router,
        private readonly Handshake                   $handshake,
        private readonly FrameCodec                  $codec,
        private readonly AuthenticateWebSocketAction $authenticateWebSocketAction,
        private readonly FinishStaleCallsAction      $finishStaleCallsAction,
    ) {
        $this->log = static function (string $line): void {
        };
    }

    /**
     * Блокирует процесс до SIGTERM/SIGINT.
     *
     * @param Closure(string): void $log
     */
    public function run(string $host, int $port, Closure $log): void
    {
        $listener = $this->listen($host, $port);

        $this->log      = $log;
        $this->listener = $listener;
        $this->running  = true;

        \pcntl_async_signals(true);
        \pcntl_signal(SIGTERM, $this->stop(...));
        \pcntl_signal(SIGINT, $this->stop(...));

        $stale = $this->finishStaleCallsAction->run();
        ($this->log)(\sprintf('Сервер сигнализации слушает ws://%s:%d (закрыто незавершённых звонков: %d)', $host, $port, $stale));

        while ($this->running) {
            $this->tick($listener);
        }

        $this->shutdown();
        ($this->log)('Сервер сигнализации остановлен');
    }

    public function stop(): void
    {
        $this->running = false;
    }

    private function tick(Socket $listener): void
    {
        $read  = [$listener, ...\array_values($this->sockets)];
        $write = [];

        foreach ($this->registry->all() as $connection) {
            if ($connection->hasOutbound() && isset($this->sockets[$connection->id])) {
                $write[] = $this->sockets[$connection->id];
            }
        }

        $except  = null;
        $changed = @\socket_select($read, $write, $except, 1);

        if ($changed === false) {
            // Сигнал прерывает select — это штатная остановка, а не ошибка
            if (\socket_last_error() !== SOCKET_EINTR) {
                ($this->log)('socket_select: ' . \socket_strerror(\socket_last_error()));
            }

            \socket_clear_error();

            return;
        }

        foreach ($read as $socket) {
            if ($socket === $listener) {
                $this->acceptClient($listener);
                continue;
            }

            $connection = $this->connectionsBySocket[\spl_object_id($socket)] ?? null;

            if ($connection !== null) {
                $this->readFrom($connection, $socket);
            }
        }

        foreach ($write as $socket) {
            $connection = $this->connectionsBySocket[\spl_object_id($socket)] ?? null;

            if ($connection !== null) {
                $this->flush($connection, $socket);
            }
        }

        $this->housekeeping(\microtime(true));
    }

    private function listen(string $host, int $port): Socket
    {
        $socket = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ($socket === false) {
            throw new RuntimeException('socket_create: ' . \socket_strerror(\socket_last_error()));
        }

        \socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

        if (!@\socket_bind($socket, $host, $port) || !\socket_listen($socket, 128)) {
            throw new RuntimeException(\sprintf('Не удалось занять %s:%d — %s', $host, $port, \socket_strerror(\socket_last_error($socket))));
        }

        \socket_set_nonblock($socket);

        return $socket;
    }

    private function acceptClient(Socket $listener): void
    {
        $socket = @\socket_accept($listener);

        if ($socket === false) {
            return;
        }

        \socket_set_nonblock($socket);
        \socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);

        $connection = new Connection($this->nextConnectionId++, $this->codec, \microtime(true));

        $this->sockets[$connection->id]                     = $socket;
        $this->connectionsBySocket[\spl_object_id($socket)] = $connection;
        $this->registry->add($connection);
    }

    private function readFrom(Connection $connection, Socket $socket): void
    {
        $data = @\socket_read($socket, self::READ_CHUNK);

        // select сказал «есть данные», а прочитать нечего — клиент закрыл соединение
        if ($data === false || $data === '') {
            $this->disconnect($connection);

            return;
        }

        $connection->inbound .= $data;
        $connection->lastActivityAt = \microtime(true);

        try {
            if (!$connection->upgraded && !$this->upgrade($connection)) {
                return;
            }

            while (!$connection->closing) {
                $frame = $this->codec->decode($connection->inbound);

                if ($frame === null) {
                    break;
                }

                $this->handleFrame($connection, $frame);
            }
        } catch (ProtocolException $exception) {
            $connection->close($exception->getCode(), $exception->getMessage());
        }
    }

    /**
     * @return bool Можно ли разбирать кадры дальше
     */
    private function upgrade(Connection $connection): bool
    {
        try {
            $request = $this->handshake->parse($connection->inbound);
        } catch (ProtocolException $exception) {
            $this->rejectHandshake($connection, $exception->getCode(), $exception->getMessage());

            return false;
        }

        if ($request === null) {
            return false;
        }

        if (!$this->isOriginAllowed($request->header('origin'))) {
            $this->rejectHandshake($connection, 403, 'Origin not allowed');

            return false;
        }

        $connection->inbound = \substr($connection->inbound, $request->length);
        $connection->queueRaw($this->handshake->accept($request));
        $connection->upgraded = true;

        // Рукопожатие завершаем и при неверном токене: так браузер получит код закрытия 4401
        // и поймёт, что переподключаться бессмысленно (ответ 401 на рукопожатие JS не видит)
        $user = $this->authenticateWebSocketAction->run($request->query('token') ?? '');

        if ($user === null) {
            $connection->close(self::CLOSE_UNAUTHORIZED, 'Unauthorized');

            return false;
        }

        $this->registry->authenticate($connection, $user);
        $connection->send(['type' => 'ready', 'data' => ['user_id' => $user->id]]);

        return true;
    }

    private function rejectHandshake(Connection $connection, int $status, string $reason): void
    {
        $connection->queueRaw($this->handshake->reject($status, $reason));
        $connection->close();
    }

    private function isOriginAllowed(?string $origin): bool
    {
        /** @var list<string> $allowed */
        $allowed = Config::array('calls.websocket.allowed_origins');

        return $allowed === [] || ($origin !== null && \in_array($origin, $allowed, true));
    }

    /**
     * @throws ProtocolException
     */
    private function handleFrame(Connection $connection, Frame $frame): void
    {
        switch ($frame->opcode) {
            case Frame::PING:
                $connection->sendFrame(Frame::PONG, $frame->payload);

                return;
            case Frame::PONG:
                return;
            case Frame::CLOSE:
                $connection->close();

                return;
        }

        $message = $connection->assemble($frame);

        if ($message === null) {
            return;
        }

        if ($message->opcode !== Frame::TEXT) {
            throw new ProtocolException('Only text messages are supported', ProtocolException::UNSUPPORTED_DATA);
        }

        if (!\mb_check_encoding($message->payload, 'UTF-8')) {
            throw new ProtocolException('Text message must be valid UTF-8', ProtocolException::INVALID_PAYLOAD);
        }

        try {
            $this->router->handle($connection, $message->payload);
        } catch (Throwable $exception) {
            report($exception);
            $connection->send(['type' => 'error', 'data' => ['request' => null, 'message' => Translator::get('exceptions.call.server_error')]]);
        }
    }

    private function flush(Connection $connection, Socket $socket): void
    {
        $written = @\socket_write($socket, $connection->outbound());

        if ($written === false) {
            $this->disconnect($connection);

            return;
        }

        $connection->consumeOutbound($written);

        if ($connection->closing && !$connection->hasOutbound()) {
            $this->disconnect($connection);
        }
    }

    private function disconnect(Connection $connection): void
    {
        $socket = $this->sockets[$connection->id] ?? null;

        unset($this->sockets[$connection->id]);

        if ($socket !== null) {
            unset($this->connectionsBySocket[\spl_object_id($socket)]);
            \socket_close($socket);
        }

        try {
            $this->router->disconnect($connection);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Раз в секунду: пропущенные вызовы, пинг молчащих клиентов, обрыв зависших соединений.
     */
    private function housekeeping(float $now): void
    {
        if ($now - $this->lastHousekeepingAt < self::HOUSEKEEPING_INTERVAL) {
            return;
        }

        $this->lastHousekeepingAt = $now;

        try {
            $this->router->tick(Config::integer('calls.ring_timeout'));
        } catch (Throwable $exception) {
            report($exception);
        }

        $pingInterval = Config::integer('calls.websocket.ping_interval');
        $idleTimeout  = Config::integer('calls.websocket.idle_timeout');

        foreach ($this->registry->all() as $connection) {
            $idle = $now - $connection->lastActivityAt;

            $isDead = ($connection->closing && !$connection->hasOutbound())
                || (!$connection->upgraded && $now - $connection->connectedAt > self::HANDSHAKE_TIMEOUT)
                || $idle > $idleTimeout;

            if ($isDead) {
                $this->disconnect($connection);
            } elseif ($connection->upgraded && $idle > $pingInterval && $now - $connection->lastPingAt > $pingInterval) {
                $connection->sendFrame(Frame::PING);
                $connection->lastPingAt = $now;
            }
        }
    }

    private function shutdown(): void
    {
        foreach ($this->registry->all() as $connection) {
            $connection->close(self::CLOSE_GOING_AWAY, 'Server shutdown');

            $socket = $this->sockets[$connection->id] ?? null;

            if ($socket !== null && $connection->hasOutbound()) {
                @\socket_write($socket, $connection->outbound());
            }

            $this->disconnect($connection);
        }

        if ($this->listener !== null) {
            \socket_close($this->listener);
            $this->listener = null;
        }
    }
}
