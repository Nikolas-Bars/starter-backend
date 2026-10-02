<?php

declare(strict_types=1);

namespace App\Modules\Push\Repositories;

use App\Modules\Push\Models\PushDevice;
use App\Repositories\BaseRepository;

/**
 * @extends BaseRepository<PushDevice>
 */
final class PushDeviceRepository extends BaseRepository
{
    /**
     * @return class-string<PushDevice>
     */
    public function model(): string
    {
        return PushDevice::class;
    }

    /**
     * Токен FCM принадлежит телефону: если на нём вошёл другой пользователь, запись переходит к нему
     */
    public function upsert(int $userId, ?int $accessTokenId, string $token): PushDevice
    {
        return $this->query()->updateOrCreate(
            ['token' => $token],
            ['user_id' => $userId, 'access_token_id' => $accessTokenId],
        );
    }

    public function existsForUser(int $userId): bool
    {
        $query = $this->query();
        $query->getQuery()->where('user_id', $userId);

        return $query->first() !== null;
    }

    /**
     * @return list<string>
     */
    public function tokensOfUser(int $userId): array
    {
        $query = $this->query();
        $query->getQuery()->where('user_id', $userId)->orderBy('id');

        /** @var list<string> */
        return $query->pluck('token')->all();
    }

    /**
     * @param list<string> $tokens
     */
    public function deleteTokens(array $tokens): void
    {
        if ($tokens === []) {
            return;
        }

        $query = $this->query();
        $query->getQuery()->whereIn('token', $tokens);
        $query->delete();
    }
}
