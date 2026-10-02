<?php

declare(strict_types=1);

namespace App\Modules\Call\Actions;

use App\Actions\BaseAction;
use App\Modules\Call\Tasks\GenerateTurnCredentialsTask;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Config;

final class GetIceServersAction extends BaseAction
{
    public function __construct(
        private readonly GenerateTurnCredentialsTask $generateTurnCredentialsTask,
    ) {
    }

    /**
     * @return list<array{urls: list<string>, username?: string, credential?: string}>
     */
    public function run(User $user): array
    {
        /** @var list<array{urls: list<string>, username?: string, credential?: string}> $servers */
        $servers = Config::array('calls.ice_servers');

        /** @var list<string> $turnUrls */
        $turnUrls = Config::array('calls.turn.urls');
        $secret   = Config::string('calls.turn.secret');

        if ($turnUrls !== [] && $secret !== '') {
            $servers[] = $this->generateTurnCredentialsTask->run($user->id, $turnUrls, $secret, Config::integer('calls.turn.ttl'));
        }

        return $servers;
    }
}
