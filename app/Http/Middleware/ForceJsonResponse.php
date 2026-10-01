<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API всегда отвечает JSON-ом, даже если клиент не прислал Accept: application/json
 * (иначе Laravel на ошибке авторизации пытается редиректить на страницу логина).
 */
final class ForceJsonResponse
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
