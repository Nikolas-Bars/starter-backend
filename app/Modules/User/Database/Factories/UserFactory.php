<?php

declare(strict_types=1);

namespace App\Modules\User\Database\Factories;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    public const DEFAULT_PASSWORD = 'Password123';

    /**
     * Хэш считается один раз на процесс: bcrypt медленный намеренно,
     * а фабрика в тестах и сидерах вызывается сотни раз.
     */
    private static ?string $passwordHash = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => self::$passwordHash ??= Hash::make(self::DEFAULT_PASSWORD),
        ];
    }

    public function unverified(): static
    {
        return $this->state(static fn(): array => [
            'email_verified_at' => null,
        ]);
    }
}
