<?php

declare(strict_types=1);

namespace App\Modules\Push\Database\Factories;

use App\Modules\Push\Models\PushDevice;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PushDevice>
 */
final class PushDeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'         => User::factory(),
            'access_token_id' => null,
            'token'           => Str::random(32) . ':' . Str::random(120),
        ];
    }
}
