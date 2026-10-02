<?php

declare(strict_types=1);

namespace App\Modules\Call\Tasks;

use App\Modules\Call\Models\Call;
use App\Tasks\BaseTask;
use Illuminate\Support\Facades\Config;

final class SignCallDeclineTask extends BaseTask
{
    /**
     * Ключ, которым телефон отклоняет вызов прямо из уведомления, без токена входа:
     * подходит только к одному звонку и только его собеседнику
     */
    public function run(Call $call): string
    {
        return \hash_hmac('sha256', 'call-decline:' . $call->id . ':' . $call->callee_id, Config::string('app.key'));
    }
}
