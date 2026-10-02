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
use App\Modules\User\Models\User;

final class ResumeCallAction extends BaseAction
{
    public function __construct(
        private readonly FindParticipantCallSubAction $findParticipantCallSubAction,
    ) {
    }

    /**
     * Участник вернулся после обрыва связи с сервером: продолжить можно только идущий разговор.
     *
     * @throws CallNotFoundException
     * @throws NotCallParticipantException
     * @throws InvalidCallStateException
     */
    public function run(User $user, int $callId): Call
    {
        $call = $this->findParticipantCallSubAction->run($user, $callId);

        if ($call->status !== CallStatusEnum::Active) {
            throw new InvalidCallStateException();
        }

        return $call;
    }
}
