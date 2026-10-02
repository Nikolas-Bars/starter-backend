<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Tasks;

use App\Tasks\BaseTask;
use Illuminate\Support\Str;

final class GenerateCallLinkCodeTask extends BaseTask
{
    /**
     * 12 символов [A-Za-z0-9] — около 71 бита: ссылку нельзя подобрать перебором.
     */
    private const LENGTH = 12;

    public function run(): string
    {
        return Str::random(self::LENGTH);
    }
}
