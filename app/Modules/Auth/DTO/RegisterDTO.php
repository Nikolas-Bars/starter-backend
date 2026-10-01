<?php

declare(strict_types=1);

namespace App\Modules\Auth\DTO;

use Spatie\LaravelData\Data;

final class RegisterDTO extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {
    }
}
