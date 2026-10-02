<?php

declare(strict_types=1);

namespace App\Modules\Call\Http\Resources;

use App\Modules\Call\Models\Call;
use App\Modules\User\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="CallResource",
 *     type="object",
 *     description="Звонок",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="status", type="string", enum={"ringing", "active", "rejected", "missed", "busy", "unavailable", "ended"}, example="ended"),
 *     @OA\Property(property="caller", ref="#/components/schemas/UserResource"),
 *     @OA\Property(property="callee", ref="#/components/schemas/UserResource"),
 *     @OA\Property(property="started_at", type="string", format="date-time"),
 *     @OA\Property(property="answered_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="ended_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="duration_seconds", type="integer", nullable=true, example=95, description="Длительность разговора; null, если не ответили")
 * )
 *
 * @mixin Call
 */
final class CallResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Call $call */
        $call = $this->resource;

        return [
            'id'               => $this->id,
            'status'           => $this->status->value,
            'caller'           => UserResource::make($this->whenLoaded('caller')),
            'callee'           => UserResource::make($this->whenLoaded('callee')),
            'started_at'       => $this->started_at->toIso8601String(),
            'answered_at'      => $this->answered_at?->toIso8601String(),
            'ended_at'         => $this->ended_at?->toIso8601String(),
            'duration_seconds' => $call->durationSeconds(),
        ];
    }
}
