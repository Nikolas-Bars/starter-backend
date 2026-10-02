<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Http\Resources;

use App\Modules\CallLink\Models\CallLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="CallLinkInviteResource",
 *     type="object",
 *     description="Чужая ссылка для звонка — то, что видит гость. Email владельца не раскрывается",
 *     @OA\Property(property="code", type="string", example="aB3dE5fG7hJ9"),
 *     @OA\Property(
 *         property="owner",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="Николай Прасолов")
 *     )
 * )
 *
 * @mixin CallLink
 */
final class CallLinkInviteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code'  => $this->code,
            'owner' => [
                'id'   => $this->owner->id,
                'name' => $this->owner->name,
            ],
        ];
    }
}
