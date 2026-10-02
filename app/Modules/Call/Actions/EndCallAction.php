<?php

declare(strict_types=1);

namespace App\Modules\Call\Actions;

use App\Actions\BaseAction;
use App\Modules\Call\Actions\SubActions\FindParticipantCallSubAction;
use App\Modules\Call\Enums\CallStatusEnum;
use App\Modules\Call\Exceptions\CallNotFoundException;
use App\Modules\Call\Exceptions\InvalidCallStateException;
use App\Modules\Call\Exceptions\NotCallParticipantException;
use App\Modules\Call\Models\Call;
use App\Modules\Call\Tasks\UpdateCallStatusTask;
use App\Modules\User\Models\User;

final class EndCallAction extends BaseAction
{
    public function __construct(
        private readonly FindParticipantCallSubAction $findParticipantCallSubAction,
        private readonly UpdateCallStatusTask         $updateCallStatusTask,
    ) {
    }

    /**
     * Положить трубку любой из сторон. Итоговый статус зависит от момента:
     * до ответа звонящий отменяет вызов (missed), а собеседник отклоняет его (rejected).
     *
     * @throws CallNotFoundException
     * @throws NotCallParticipantException
     * @throws InvalidCallStateException
     */
    public function run(User $user, int $callId): Call
    {
        $call = $this->findParticipantCallSubAction->run($user, $callId);

        if (!$call->status->isOngoing()) {
            throw new InvalidCallStateException();
        }

        $status = match (true) {
            $call->status === CallStatusEnum::Active => CallStatusEnum::Ended,
            $call->caller_id === $user->id           => CallStatusEnum::Missed,
            default                                  => CallStatusEnum::Rejected,
        };

        return $this->updateCallStatusTask->run($call, $status);
    }
}
