<?php

declare(strict_types=1);

namespace App\Modules\Call\Database\Factories;

use App\Modules\Call\Enums\CallStatusEnum;
use App\Modules\Call\Models\Call;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Call>
 */
final class CallFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'caller_id'  => User::factory(),
            'callee_id'  => User::factory(),
            'status'     => CallStatusEnum::Ringing,
            'started_at' => now(),
        ];
    }

    public function between(User $caller, User $callee): static
    {
        return $this->state([
            'caller_id' => $caller->id,
            'callee_id' => $callee->id,
        ]);
    }

    public function active(): static
    {
        return $this->state([
            'status'      => CallStatusEnum::Active,
            'answered_at' => now(),
        ]);
    }

    public function ended(int $durationSeconds = 60): static
    {
        return $this->state([
            'status'      => CallStatusEnum::Ended,
            'answered_at' => now()->subSeconds($durationSeconds),
            'ended_at'    => now(),
        ]);
    }
}
