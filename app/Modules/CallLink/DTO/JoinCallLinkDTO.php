<?php

declare(strict_types=1);

namespace App\Modules\CallLink\DTO;

use Spatie\LaravelData\Data;

final class JoinCallLinkDTO extends Data
{
    /**
     * @param string $name Как гостя увидит владелец ссылки
     */
    public function __construct(
        public string $name,
        public string $device_name = 'web',
    ) {
    }
}
