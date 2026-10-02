<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Http\Resources;

use App\Modules\CallLink\Models\CallLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="CallLinkResource",
 *     type="object",
 *     description="Своя ссылка для звонка. Адрес собирает клиент: {адрес фронтенда}/c/{code}",
 *     @OA\Property(property="code", type="string", example="aB3dE5fG7hJ9"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true, description="Когда ссылку выпустили")
 * )
 *
 * @mixin CallLink
 */
final class CallLinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code'       => $this->code,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
