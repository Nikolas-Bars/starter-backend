<?php

declare(strict_types=1);

namespace App\Modules\Vpn\DTO;

use Spatie\LaravelData\Data;

final class VpnProfileDTO extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $uri,
        public readonly int $position = 0,
        public readonly bool $isEnabled = true,
    ) {
    }
}
