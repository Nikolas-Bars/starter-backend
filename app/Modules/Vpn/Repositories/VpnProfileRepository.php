<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Repositories;

use App\Modules\Vpn\DTO\VpnProfileDTO;
use App\Modules\Vpn\Models\VpnProfile;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<VpnProfile>
 */
final class VpnProfileRepository extends BaseRepository
{
    /**
     * @return class-string<VpnProfile>
     */
    public function model(): string
    {
        return VpnProfile::class;
    }

    /**
     * @return Collection<int, VpnProfile>
     */
    public function listOrdered(bool $onlyEnabled): Collection
    {
        $query = $this->query();

        if ($onlyEnabled) {
            $query->getQuery()->where('is_enabled', true);
        }

        $query->getQuery()->orderBy('position')->orderBy('id');

        return $query->get();
    }

    public function store(VpnProfileDTO $dto): VpnProfile
    {
        return $this->create([
            'name'       => $dto->name,
            'uri'        => $dto->uri,
            'position'   => $dto->position,
            'is_enabled' => $dto->isEnabled,
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(VpnProfile $profile, array $attributes): VpnProfile
    {
        $profile->update($attributes);

        return $profile;
    }

    public function delete(VpnProfile $profile): void
    {
        $profile->delete();
    }
}
