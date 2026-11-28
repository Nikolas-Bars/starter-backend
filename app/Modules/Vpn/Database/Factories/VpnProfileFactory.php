<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Database\Factories;

use App\Modules\Vpn\Models\VpnProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VpnProfile>
 */
final class VpnProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'VPN ' . fake()->unique()->numberBetween(1, 999),
            'uri'  => \sprintf(
                'vless://%s@vpn.example.com:443?type=tcp&security=reality&pbk=%s&sid=ab12&sni=www.example.com&fp=chrome&flow=xtls-rprx-vision#test',
                Str::uuid()->toString(),
                Str::random(43),
            ),
            'position'   => 0,
            'is_enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn(): array => ['is_enabled' => false]);
    }
}
