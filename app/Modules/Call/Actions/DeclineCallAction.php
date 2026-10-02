<?php

declare(strict_types=1);

namespace App\Modules\Call\Actions;

use App\Actions\BaseAction;
use App\Modules\Call\Enums\CallStatusEnum;
use App\Modules\Call\Exceptions\CallNotFoundException;
use App\Modules\Call\Exceptions\InvalidCallStateException;
use App\Modules\Call\Models\Call;
use App\Modules\Call\Tasks\FindCallTask;
use App\Modules\Call\Tasks\NotifyCallFinishedTask;
use App\Modules\Call\Tasks\SignCallDeclineTask;
use App\Modules\Call\Tasks\UpdateCallStatusTask;

final class DeclineCallAction extends BaseAction
{
    public function __construct(
        private readonly FindCallTask           $findCallTask,
        private readonly SignCallDeclineTask    $signCallDeclineTask,
        private readonly UpdateCallStatusTask   $updateCallStatusTask,
        private readonly NotifyCallFinishedTask $notifyCallFinishedTask,
    ) {
    }

    /**
     * «Отклонить» в уведомлении о звонке: приложение может быть даже не запущено,
     * поэтому вместо токена входа — ключ из push. Чужой ключ неотличим от несуществующего звонка.
     *
     * @throws CallNotFoundException
     * @throws InvalidCallStateException
     */
    public function run(int $callId, string $token): Call
    {
        $call = $this->findCallTask->run($callId);

        if ($call === null || !\hash_equals($this->signCallDeclineTask->run($call), $token)) {
            throw new CallNotFoundException();
        }

        if ($call->status !== CallStatusEnum::Ringing) {
            throw new InvalidCallStateException();
        }

        $call = $this->updateCallStatusTask->run($call, CallStatusEnum::Rejected);
        $this->notifyCallFinishedTask->run($call->id);

        return $call;
    }
}
