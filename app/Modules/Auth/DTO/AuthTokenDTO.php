<?php

declare(strict_types=1);

namespace App\Modules\Auth\DTO;

use App\Modules\User\Models\User;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

final class AuthTokenDTO extends Data
{
    /**
     * @param string $access_token Токен в открытом виде — в БД хранится только его SHA-256
     */
    public function __construct(
        public string  $access_token,
        public ?Carbon $expires_at,
        public User    $user,
        public string  $token_type = 'Bearer',
    ) {
    }
}
