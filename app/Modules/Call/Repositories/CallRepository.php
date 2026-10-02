<?php

declare(strict_types=1);

namespace App\Modules\Call\Repositories;

use App\Modules\Call\Enums\CallStatusEnum;
use App\Modules\Call\Models\Call;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * @extends BaseRepository<Call>
 */
final class CallRepository extends BaseRepository
{
    private const PARTICIPANTS = ['caller', 'callee'];

    /**
     * @return class-string<Call>
     */
    public function model(): string
    {
        return Call::class;
    }

    public function findWithParticipants(int $id): ?Call
    {
        return $this->query()->with(self::PARTICIPANTS)->find($id);
    }

    /**
     * Звонок, в котором пользователь участвует прямо сейчас (идёт вызов или разговор).
     */
    public function findOngoingForUser(int $userId): ?Call
    {
        return $this->query()
            ->with(self::PARTICIPANTS)
            ->where(static fn(Builder $query): Builder => $query
                ->where('status', CallStatusEnum::Ringing)
                ->orWhere('status', CallStatusEnum::Active))
            ->where(static fn(Builder $query): Builder => $query
                ->where('caller_id', $userId)
                ->orWhere('callee_id', $userId))
            ->latest('started_at')
            ->first();
    }

    public function store(int $callerId, int $calleeId, CallStatusEnum $status, Carbon $startedAt): Call
    {
        $call = $this->create([
            'caller_id'  => $callerId,
            'callee_id'  => $calleeId,
            'status'     => $status,
            'started_at' => $startedAt,
            'ended_at'   => $status->isOngoing() ? null : $startedAt,
        ]);

        return $call->load(self::PARTICIPANTS);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(Call $call, array $attributes): Call
    {
        $call->update($attributes);

        return $call->loadMissing(self::PARTICIPANTS);
    }

    /**
     * @return Collection<int, Call>
     */
    public function findRingingStartedBefore(Carbon $moment): Collection
    {
        return $this->query()
            ->with(self::PARTICIPANTS)
            ->where('status', CallStatusEnum::Ringing)
            ->where('started_at', '<=', $moment)
            ->get();
    }

    /**
     * @param list<int> $ids
     */
    public function finishMany(array $ids, CallStatusEnum $status, Carbon $endedAt): void
    {
        if ($ids === []) {
            return;
        }

        $this->query()->whereKey($ids)->update([
            'status'   => $status,
            'ended_at' => $endedAt,
        ]);
    }

    /**
     * Закрывает звонки, оставшиеся незавершёнными (например, после падения сервера сигнализации).
     */
    public function finishAllOngoing(Carbon $endedAt): int
    {
        $missed = $this->query()
            ->where('status', CallStatusEnum::Ringing)
            ->update(['status' => CallStatusEnum::Missed, 'ended_at' => $endedAt]);

        $ended = $this->query()
            ->where('status', CallStatusEnum::Active)
            ->update(['status' => CallStatusEnum::Ended, 'ended_at' => $endedAt]);

        return $missed + $ended;
    }

    /**
     * @return LengthAwarePaginator<int, Call>
     */
    public function paginateForUser(int $userId, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->with(self::PARTICIPANTS)
            ->where(static fn(Builder $query): Builder => $query
                ->where('caller_id', $userId)
                ->orWhere('callee_id', $userId))
            ->latest('started_at')
            ->latest('id')
            ->paginate($perPage);
    }
}
