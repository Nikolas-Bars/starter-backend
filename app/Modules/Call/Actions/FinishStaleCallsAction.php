<?php

declare(strict_types=1);

namespace App\Modules\Call\Actions;

use App\Actions\BaseAction;
use App\Modules\Call\Tasks\FinishOngoingCallsTask;

final class FinishStaleCallsAction extends BaseAction
{
    public function __construct(
        private readonly FinishOngoingCallsTask $finishOngoingCallsTask,
    ) {
    }

    /**
     * Вызывается при старте сервера сигнализации: соединений ещё нет,
     * значит все «идущие» звонки остались от прошлого запуска.
     *
     * @return int Сколько звонков закрыто
     */
    public function run(): int
    {
        return $this->finishOngoingCallsTask->run();
    }
}
