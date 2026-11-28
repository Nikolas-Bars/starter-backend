<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Database\Factories;

use App\Modules\User\Models\User;
use App\Modules\Vpn\Models\VpnGrant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VpnGrant>
 */
final class VpnGrantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
        ];
    }
}
