<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repositories;

use App\Modules\User\Models\User;
use App\Repositories\BaseRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
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

    /**
     * Владелец действующего токена. Те же проверки срока, что делает guard Sanctum для Bearer-запросов.
     */
    public function findUserByToken(string $plainTextToken, ?int $expirationMinutes): ?User
    {
        $token = PersonalAccessToken::findToken($plainTextToken);

        if ($token === null || $token->expires_at?->isPast() === true) {
            return null;
        }

        if ($expirationMinutes !== null && $token->created_at?->lte(Date::now()->subMinutes($expirationMinutes)) === true) {
            return null;
        }

        return $token->tokenable instanceof User ? $token->tokenable : null;
    }

    public function revoke(PersonalAccessToken $token): void
    {
        $token->delete();
    }
}
