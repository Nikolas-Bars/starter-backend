<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Resources;

use App\Modules\Auth\DTO\AuthTokenDTO;
use App\Modules\User\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="AuthTokenResource",
 *     type="object",
 *     description="Токен доступа и пользователь",
 *     @OA\Property(property="access_token", type="string", example="1|Tq3x...k9P"),
 *     @OA\Property(property="token_type", type="string", example="Bearer"),
 *     @OA\Property(property="expires_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="user", ref="#/components/schemas/UserResource")
 * )
 *
 * @property AuthTokenDTO $resource
 */
final class AuthTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this->resource->access_token,
            'token_type'   => $this->resource->token_type,
            'expires_at'   => $this->resource->expires_at?->toIso8601String(),
            'user'         => UserResource::make($this->resource->user)->withEmail()->resolve($request),
        ];
    }
}
