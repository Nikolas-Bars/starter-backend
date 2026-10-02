<?php

declare(strict_types=1);

namespace App\Modules\Call\Actions\SubActions;

use App\Actions\BaseAction;
use App\Modules\Call\Exceptions\CallNotFoundException;
use App\Modules\Call\Exceptions\NotCallParticipantException;
use App\Modules\Call\Models\Call;
use App\Modules\Call\Tasks\FindCallTask;
use App\Modules\User\Models\User;

final class FindParticipantCallSubAction extends BaseAction
{
    public function __construct(
        private readonly FindCallTask $findCallTask,
    ) {
    }

    /**
     * @throws CallNotFoundException
     * @throws NotCallParticipantException
     */
    public function run(User $user, int $callId): Call
    {
        $call = $this->findCallTask->run($callId);

        if ($call === null) {
            throw new CallNotFoundException();
        }

        if (!$call->isParticipant($user->id)) {
            throw new NotCallParticipantException();
        }

        return $call;
    }
}
