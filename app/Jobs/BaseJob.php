<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Job — работа, вынесенная в очередь (воркер queue:work). Внутри — вызов одного Action из handle().
 * Нужна, когда вызывающий не может ждать: например, сервер сигнализации не должен блокироваться
 * на сетевых запросах.
 */
abstract class BaseJob implements ShouldQueue
{
    use Queueable;
}
