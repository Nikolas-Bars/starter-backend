<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Repositories;

use App\Modules\CallLink\Models\CallLink;
use App\Repositories\BaseRepository;

/**
 * @extends BaseRepository<CallLink>
 */
final class CallLinkRepository extends BaseRepository
{
    /**
     * @return class-string<CallLink>
     */
    public function model(): string
    {
        return CallLink::class;
    }

    public function findByUserId(int $userId): ?CallLink
    {
        return $this->query()->where('user_id', $userId)->first();
    }

    public function findByCodeWithOwner(string $code): ?CallLink
    {
        return $this->query()->with('owner')->where('code', $code)->first();
    }

    public function store(int $userId, string $code): CallLink
    {
        return $this->create([
            'user_id' => $userId,
            'code'    => $code,
        ]);
    }

    public function updateCode(CallLink $link, string $code): CallLink
    {
        $link->update(['code' => $code]);

        return $link;
    }
}
