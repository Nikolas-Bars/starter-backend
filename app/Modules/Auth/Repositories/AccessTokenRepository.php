<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repositories;

use App\Modules\User\Models\User;
use App\Repositories\BaseRepository;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Токены Sanctum (таблица personal_access_tokens).
 *
 * @extends BaseRepository<PersonalAccessToken>
 */
final class AccessTokenRepository extends BaseRepository
{
    /**
     * @return class-string<PersonalAccessToken>
     */
    public function model(): string
    {
        return PersonalAccessToken::class;
    }

    public function issue(User $user, string $name, ?Carbon $expiresAt): NewAccessToken
    {
        return $user->createToken($name, ['*'], $expiresAt);
    }

    public function revoke(PersonalAccessToken $token): void
    {
        $token->delete();
    }
}
