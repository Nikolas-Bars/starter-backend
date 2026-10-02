<?php

declare(strict_types=1);

namespace App\Modules\Call\Console\Commands;

use App\Modules\Call\WebSockets\Server;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

final class ServeWebSocketCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'call:ws-serve
        {--host= : Адрес, по умолчанию calls.websocket.host}
        {--port= : Порт, по умолчанию calls.websocket.port}';

    /**
     * @var string
     */
    protected $description = 'Запускает WebSocket-сервер сигнализации видеозвонков';

    public function handle(Server $server): int
    {
        $host = $this->option('host');
        $port = $this->option('port');

        $server->run(
            \is_string($host) && $host !== '' ? $host : Config::string('calls.websocket.host'),
            \is_numeric($port) ? (int)$port : Config::integer('calls.websocket.port'),
            function (string $line): void {
                $this->components->info($line);
            },
        );

        return self::SUCCESS;
    }
}
