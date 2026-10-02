<?php

declare(strict_types=1);

namespace App\Modules\Call\Actions;

use App\Actions\BaseAction;
use App\Modules\Call\Enums\CallStatusEnum;
use App\Modules\Call\Exceptions\InvalidCalleeException;
use App\Modules\Call\Exceptions\UserBusyException;
use App\Modules\Call\Models\Call;
use App\Modules\Call\Tasks\CreateCallTask;
use App\Modules\Call\Tasks\FindActiveCallForUserTask;
use App\Modules\User\Models\User;
use App\Modules\User\Tasks\FindUserByIdTask;
use Illuminate\Support\Facades\DB;

final class InitiateCallAction extends BaseAction
{
    public function __construct(
        private readonly FindUserByIdTask          $findUserByIdTask,
        private readonly FindActiveCallForUserTask $findActiveCallForUserTask,
        private readonly CreateCallTask            $createCallTask,
    ) {
    }

    /**
     * Звонок создаётся всегда — даже если собеседник не в сети или занят: такой вызов
     * сразу получает финальный статус и попадает в историю обоих пользователей.
     *
     * @param bool $calleeOnline Есть ли у собеседника открытое соединение с сервером сигнализации
     *
     * @throws InvalidCalleeException
     * @throws UserBusyException
     */
    public function run(User $caller, int $calleeId, bool $calleeOnline): Call
    {
        $callee = $calleeId === $caller->id ? null : $this->findUserByIdTask->run($calleeId);

        if ($callee === null || !$caller->canContact($callee)) {
            throw new InvalidCalleeException();
        }

        return DB::transaction(function () use ($caller, $calleeId, $calleeOnline): Call {
            if ($this->findActiveCallForUserTask->run($caller->id) !== null) {
                throw new UserBusyException();
            }

            $status = match (true) {
                !$calleeOnline                                            => CallStatusEnum::Unavailable,
                $this->findActiveCallForUserTask->run($calleeId) !== null => CallStatusEnum::Busy,
                default                                                   => CallStatusEnum::Ringing,
            };

            return $this->createCallTask->run($caller->id, $calleeId, $status);
        });
    }
}
