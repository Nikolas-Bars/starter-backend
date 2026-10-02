<?php

declare(strict_types=1);

namespace App\Modules\Call\Actions;

use App\Actions\BaseAction;
use App\Modules\Call\Enums\CallPushEventEnum;
use App\Modules\Call\Enums\CallStatusEnum;
use App\Modules\Call\Tasks\FindCallTask;
use App\Modules\Call\Tasks\SignCallDeclineTask;
use App\Modules\Push\Tasks\SendPushToUserTask;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;

final class SendCallPushAction extends BaseAction
{
    public function __construct(
        private readonly FindCallTask        $findCallTask,
        private readonly SignCallDeclineTask $signCallDeclineTask,
        private readonly SendPushToUserTask  $sendPushToUserTask,
    ) {
    }

    /**
     * Будит телефоны собеседника входящим вызовом или убирает уведомление о нём.
     * Очередь могла задержать отправку: о вызове, который уже не звонит, не сообщаем.
     */
    public function run(int $callId, CallPushEventEnum $event): void
    {
        $call = $this->findCallTask->run($callId);

        if ($call === null) {
            return;
        }

        if ($event === CallPushEventEnum::Ended) {
            $this->sendPushToUserTask->run($call->callee_id, [
                'type'    => 'call.ended',
                'call_id' => (string)$call->id,
            ], Config::integer('push.call_ttl') * 2);

            return;
        }

        if ($call->status !== CallStatusEnum::Ringing) {
            return;
        }

        $ringUntil = $call->started_at->copy()->addSeconds(Config::integer('calls.ring_timeout'));
        $ttl       = (int)\min(Config::integer('push.call_ttl'), \max(0, Date::now()->diffInSeconds($ringUntil, false)));

        if ($ttl <= 0) {
            return;
        }

        $this->sendPushToUserTask->run($call->callee_id, [
            'type'          => 'call.incoming',
            'call_id'       => (string)$call->id,
            'caller_id'     => (string)$call->caller_id,
            'caller_name'   => $call->caller->name,
            'decline_token' => $this->signCallDeclineTask->run($call),
            // Телефон не показывает вызов, если push пришёл позже (миллисекунды Unix)
            'expires_at' => (string)$ringUntil->getTimestampMs(),
        ], $ttl);
    }
}
