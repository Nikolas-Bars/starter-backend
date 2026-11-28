<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Http\Resources;

use App\Modules\Vpn\Models\VpnProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="VpnProfileResource",
 *     type="object",
 *     description="Профиль VPN для ядра Xray в приложении: uri — ссылка (vless, vmess, trojan, ss, hysteria2, socks) или JSON исходящих Xray ({\"outbounds\": [...]} или один outbound)",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Основной · TCP"),
 *     @OA\Property(property="uri", type="string", example="vless://2f1c…@185.17.1.109:2083?type=tcp&security=reality&pbk=…&sid=…&sni=www.samsung.com&fp=chrome&flow=xtls-rprx-vision")
 * )
 *
 * @mixin VpnProfile
 */
final class VpnProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
            'uri'  => $this->uri,
        ];
    }
}
