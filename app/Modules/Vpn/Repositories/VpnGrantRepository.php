<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Repositories;

use App\Modules\Vpn\Models\VpnGrant;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<VpnGrant>
 */
final class VpnGrantRepository extends BaseRepository
{
    /**
     * @return class-string<VpnGrant>
     */
    public function model(): string
    {
        return VpnGrant::class;
    }

    public function existsForUser(int $userId): bool
    {
        return $this->query()->where('user_id', $userId)->getQuery()->exists();
    }

    /**
     * @return bool Создана ли запись (false — доступ уже был)
     */
    public function grant(int $userId): bool
    {
        return $this->query()->firstOrCreate(['user_id' => $userId])->wasRecentlyCreated;
    }

    /**
     * @return bool Был ли доступ до отзыва
     */
    public function revoke(int $userId): bool
    {
        return $this->query()->where('user_id', $userId)->delete() > 0;
    }

    /**
     * @return Collection<int, VpnGrant>
     */
    public function listWithUsers(): Collection
    {
        return $this->query()->with('user')->oldest()->get();
    }
}
