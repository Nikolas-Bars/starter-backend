<?php

declare(strict_types=1);

namespace App\Modules\User\DTO;

use Spatie\LaravelData\Data;

final class UpdateProfileDTO extends Data
{
    /**
     * @param string|null $username Ник в нижнем регистре без @; null — ника нет
     */
    public function __construct(
        public string  $name,
        public ?string $username,
    ) {
    }
}
