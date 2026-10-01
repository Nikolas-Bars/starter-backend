<?php

declare(strict_types=1);

namespace App\Modules\User\Database\Seeders;

use App\Modules\User\Database\Factories\UserFactory;
use App\Modules\User\Models\User;
use Illuminate\Database\Seeder;

/**
 * Моковые пользователи для локальной разработки.
 * У всех пароль UserFactory::DEFAULT_PASSWORD (Password123).
 */
final class UserSeeder extends Seeder
{
    private const FIXED_USERS = [
        ['name' => 'Администратор', 'email' => 'admin@example.com'],
        ['name' => 'Иван Петров', 'email' => 'ivan@example.com'],
        ['name' => 'Мария Смирнова', 'email' => 'maria@example.com'],
    ];

    private const RANDOM_USERS_COUNT = 10;

    public function run(): void
    {
        foreach (self::FIXED_USERS as $user) {
            User::factory()->create($user);
        }

        User::factory()->count(self::RANDOM_USERS_COUNT)->create();

        $this->command->info(\sprintf(
            'Создано пользователей: %d. Пароль у всех: %s',
            \count(self::FIXED_USERS) + self::RANDOM_USERS_COUNT,
            UserFactory::DEFAULT_PASSWORD,
        ));
    }
}
