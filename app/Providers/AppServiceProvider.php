<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\FileUrlSigner;
use App\Services\MediaProcessor;
use App\Services\RealtimeBus;
use App\Services\TelegramNotifier;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RealtimeBus::class);
        $this->app->singleton(FileUrlSigner::class, static fn(): FileUrlSigner => new FileUrlSigner(Config::string('app.key')));
        $this->app->bind(TelegramNotifier::class, static fn(Application $app): TelegramNotifier => new TelegramNotifier(
            $app->make(HttpFactory::class),
            self::nullableString(Config::get('services.telegram.bot_token')),
            self::nullableString(Config::get('services.telegram.chat_id')),
        ));
        $this->app->bind(static function (): MediaProcessor {
            /** @var array{max_side: int, thumb_side: int, quality: int, thumb_quality: int} $image */
            $image = Config::array('attachments.image');
            /** @var array{max_side: int, crf: int, audio_kbps: int, threads: int} $video */
            $video = Config::array('attachments.video');
            /** @var array{audio_kbps: int, waveform_peaks: int} $voice */
            $voice = Config::array('attachments.voice');
            /** @var array{side: int, quality: int} $avatar */
            $avatar = Config::array('attachments.avatar');

            return new MediaProcessor(
                Config::string('attachments.ffmpeg'),
                Config::string('attachments.ffprobe'),
                Config::integer('attachments.process_timeout'),
                $image,
                $video,
                $voice,
                $avatar,
            );
        });
        $this->registerModuleServiceProviders();
    }

    public function boot(): void
    {
        $this->registerModuleFactoryResolvers();
        $this->configureTrustedProxies();
    }

    private static function nullableString(mixed $value): ?string
    {
        return \is_string($value) || \is_int($value) ? (string)$value : null;
    }

    private function configureTrustedProxies(): void
    {
        $proxies = \trim(Config::string('app.trusted_proxies'));

        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : \array_map('trim', \explode(',', $proxies)));
        }
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
