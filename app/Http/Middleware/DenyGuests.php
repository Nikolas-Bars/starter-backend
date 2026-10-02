<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\User\Exceptions\GuestNotAllowedException;
use App\Modules\User\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Гость по ссылке для звонка может только позвонить владельцу ссылки: остальное API ему закрыто.
 * Ставится после auth:sanctum — `#[Get('users', middleware: ['auth:sanctum', 'not_guest'])]`.
 */
final class DenyGuests
{
    /**
     * @param Closure(Request): Response $next
     *
     * @throws GuestNotAllowedException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isGuest()) {
            throw new GuestNotAllowedException();
        }

        return $next($request);
    }
}
