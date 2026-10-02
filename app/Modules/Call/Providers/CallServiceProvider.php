<?php

declare(strict_types=1);

namespace App\Modules\Call\Providers;

use App\Modules\Call\WebSockets\ConnectionRegistry;
use App\Modules\Call\WebSockets\FrameCodec;
use App\Providers\LoadModuleProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;

final class CallServiceProvider extends LoadModuleProvider
{
    public function register(): void
    {
        // Сервер и маршрутизатор сообщений должны видеть один и тот же список соединений
        $this->app->singleton(ConnectionRegistry::class);

        $this->app->singleton(FrameCodec::class, static fn(): FrameCodec => new FrameCodec(
            Config::integer('calls.websocket.max_message_size'),
        ));
    }

    public function boot(): void
    {
        parent::boot();

        // Отклонение из уведомления открыто без входа: подбирать ключи не даём
        RateLimiter::for('call-decline', static fn(Request $request): Limit => Limit::perMinute(30)->by((string)$request->ip()));
    }
}
