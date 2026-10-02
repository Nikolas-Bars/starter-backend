<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Resources;

use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="UserResource",
 *     type="object",
 *     description="Пользователь",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Иван Петров"),
 *     @OA\Property(property="username", type="string", nullable=true, example="ivan_petrov", description="Ник без @"),
 *     @OA\Property(property="email", type="string", format="email", nullable=true, example="ivan@example.com", description="Только у текущего пользователя, у остальных null"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="is_guest", type="boolean", example=false, description="Гость по ссылке для звонка: email технический, показывать его не нужно")
 * )
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    private bool $withEmail = false;

    /**
     * Email — личные данные: отдаём его только самому пользователю (вход, профиль)
     */
    public function withEmail(): self
    {
        $this->withEmail = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'username'          => $this->username,
            'email'             => $this->withEmail ? $this->email : null,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at'        => $this->created_at?->toIso8601String(),
            'is_guest'          => $this->guest_of_id !== null,
        ];
    }
}
