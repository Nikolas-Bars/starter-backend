<?php

declare(strict_types=1);

namespace App\Modules\Call\Actions;

use App\Actions\BaseAction;
use Illuminate\Support\Facades\Config;

final class GetIceServersAction extends BaseAction
{
    /**
     * @return list<array{urls: list<string>, username?: string, credential?: string}>
     */
    public function run(): array
    {
        /** @var list<array{urls: list<string>, username?: string, credential?: string}> */
        return Config::array('calls.ice_servers');
    }
}
