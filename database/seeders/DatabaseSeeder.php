<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\User\Database\Seeders\UserSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Моковые пользователи с общим паролем из публичного репозитория — на проде это открытые аккаунты
        if (App::isProduction()) {
            $this->command->warn('Продакшн: моковые пользователи не создаются.');

            return;
        }

        $this->call([
            UserSeeder::class,
        ]);
    }
}
