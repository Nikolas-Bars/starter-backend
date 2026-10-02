<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Providers;

use App\Providers\LoadModuleProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;

final class CallLinkServiceProvider extends LoadModuleProvider
{
    public function boot(): void
    {
        parent::boot();

        // Вход по ссылке создаёт пользователя: ограничиваем, чтобы ссылкой нельзя было засыпать базу
        RateLimiter::for('call-link-join', static fn(Request $request): Limit => Limit::perMinute(Config::integer('calls.links.join_attempts_per_minute'))
            ->by((string)$request->ip()));
    }
}
