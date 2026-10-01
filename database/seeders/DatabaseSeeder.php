<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\User\Database\Seeders\UserSeeder;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
        ]);
    }
}
