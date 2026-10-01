<?php

declare(strict_types=1);

namespace App\Modules\Auth\Providers;

use App\Providers\LoadModuleProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class AuthServiceProvider extends LoadModuleProvider
{
    private const LOGIN_ATTEMPTS_PER_MINUTE = 5;

    private const REGISTER_ATTEMPTS_PER_MINUTE = 10;

    public function boot(): void
    {
        parent::boot();

        // Подбор пароля к одному аккаунту ограничиваем по паре email + IP
        RateLimiter::for('login', static fn(Request $request): Limit => Limit::perMinute(self::LOGIN_ATTEMPTS_PER_MINUTE)
            ->by(\mb_strtolower($request->string('email')->toString()) . '|' . $request->ip()));

        RateLimiter::for('register', static fn(Request $request): Limit => Limit::perMinute(self::REGISTER_ATTEMPTS_PER_MINUTE)
            ->by((string)$request->ip()));
    }
}
