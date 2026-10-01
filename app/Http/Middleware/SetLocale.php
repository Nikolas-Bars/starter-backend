<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Язык ответа: заголовок X-Locale, затем Accept-Language. Всё, чего нет
 * в app.supported_locales, падает на первую локаль из списка.
 */
final class SetLocale
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $supported */
        $supported = config('app.supported_locales', ['ru']);

        $requested = $request->header('X-Locale') ?? $request->getPreferredLanguage($supported);

        app()->setLocale(\in_array($requested, $supported, true) ? $requested : $supported[0]);

        return $next($request);
    }
}
