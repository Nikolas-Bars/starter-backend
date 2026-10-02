<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Database\Factories;

use App\Modules\CallLink\Models\CallLink;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CallLink>
 */
final class CallLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'code'    => Str::random(12),
        ];
    }
}
