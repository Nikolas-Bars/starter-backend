<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Базовый провайдер модуля: подключает миграции из {Module}/Database/Migrations
 * и artisan-команды из {Module}/Console/Commands.
 * Маршруты регистрируются атрибутами на контроллерах (config/route-attributes.php).
 */
abstract class LoadModuleProvider extends ServiceProvider
{
    public function boot(): void
    {
        $migrationPath = $this->getModuleDir() . '/Database/Migrations';

        if (\is_dir($migrationPath)) {
            $this->loadMigrationsFrom($migrationPath);
        }

        if ($this->app->runningInConsole()) {
            $this->commands($this->moduleCommands());
        }
    }

    /**
     * @return list<string>
     */
    private function moduleCommands(): array
    {
        $commandFiles = \glob($this->getModuleDir() . '/Console/Commands/*.php');
        $namespace    = Str::beforeLast(Str::beforeLast(static::class, '\\'), '\\Providers') . '\\Console\\Commands\\';

        return \array_map(
            static fn(string $file): string => $namespace . \basename($file, '.php'),
            $commandFiles === false ? [] : $commandFiles,
        );
    }

    protected function getModuleDir(): string
    {
        // App\Modules\User\Providers\UserServiceProvider => app/Modules/User
        $moduleNamespace = Str::beforeLast(Str::beforeLast(static::class, '\\'), '\\Providers');
        $relative        = \str_replace('\\', DIRECTORY_SEPARATOR, Str::replaceFirst('App\\', 'app\\', $moduleNamespace));

        return base_path($relative);
    }
}
