<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tasks;

use App\Modules\Auth\DTO\AuthTokenDTO;
use App\Modules\Auth\Repositories\AccessTokenRepository;
use App\Modules\User\Models\User;
use App\Tasks\BaseTask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

final class IssueAccessTokenTask extends BaseTask
{
    public function __construct(
        private readonly AccessTokenRepository $repository,
    ) {
    }

    public function run(User $user, string $deviceName): AuthTokenDTO
    {
        $expiresAt = $this->expiresAt();
        $token     = $this->repository->issue($user, $deviceName, $expiresAt);

        return new AuthTokenDTO(
            access_token: $token->plainTextToken,
            expires_at: $expiresAt,
            user: $user,
        );
    }

    private function expiresAt(): ?Carbon
    {
        $minutes = config('sanctum.expiration');

        return \is_int($minutes) ? Date::now()->addMinutes($minutes) : null;
    }
}
