<?php

declare(strict_types=1);

namespace App\Modules\Chat\Providers;

use App\Providers\LoadModuleProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class ChatServiceProvider extends LoadModuleProvider
{
    private const MESSAGES_PER_MINUTE = 60;

    private const REACTIONS_PER_MINUTE = 120;

    public function boot(): void
    {
        parent::boot();

        RateLimiter::for('chat-message', static fn(Request $request): Limit => self::perUser($request, self::MESSAGES_PER_MINUTE));
        RateLimiter::for('chat-reaction', static fn(Request $request): Limit => self::perUser($request, self::REACTIONS_PER_MINUTE));
    }

    private static function perUser(Request $request, int $perMinute): Limit
    {
        $userId = $request->user()?->getAuthIdentifier();

        return Limit::perMinute($perMinute)->by(\is_int($userId) ? 'user:' . $userId : 'ip:' . $request->ip());
    }
}
