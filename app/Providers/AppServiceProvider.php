<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerModuleServiceProviders();
    }

    public function boot(): void
    {
        $this->registerModuleFactoryResolvers();
    }

    /**
     * Каждый модуль подключается своим провайдером из {Module}/Providers —
     * новый модуль не нужно никуда прописывать вручную.
     */
    private function registerModuleServiceProviders(): void
    {
        $providerFiles = \glob(app_path('Modules/*/Providers/*.php'));

        foreach ($providerFiles === false ? [] : $providerFiles as $providerFile) {
            $module = \basename(\dirname($providerFile, 2));

            $this->app->register('App\\Modules\\' . $module . '\\Providers\\' . \basename($providerFile, '.php'));
        }
    }

    /**
     * Связывает модели и фабрики по структуре модуля:
     * App\Modules\User\Models\User <=> App\Modules\User\Database\Factories\UserFactory.
     */
    private function registerModuleFactoryResolvers(): void
    {
        Factory::guessModelNamesUsing(static function (Factory $factory): string {
            /** @var class-string<Model> */
            return Str::of($factory::class)
                ->replaceFirst('Database\\Factories', 'Models')
                ->replaceLast('Factory', '')
                ->toString();
        });

        Factory::guessFactoryNamesUsing(static function (string $modelName): string {
            /** @var class-string<Factory<Model>> */
            return Str::of($modelName)
                ->replaceFirst('Models', 'Database\\Factories')
                ->append('Factory')
                ->toString();
        });
    }
}
