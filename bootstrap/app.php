<?php

declare(strict_types=1);

use App\Exceptions\ApiException;
use App\Http\Middleware\DenyGuests;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SetLocale;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__))
    ->withRouting(
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(static function (Middleware $middleware): void {
        $middleware->group('api', [
            ForceJsonResponse::class,
            SetLocale::class,
            SubstituteBindings::class,
        ]);

        $middleware->alias(['not_guest' => DenyGuests::class]);
    })
    ->withExceptions(static function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(static fn(Request $request): bool => $request->is('api/*') || $request->expectsJson());

        // ApiException — штатный ответ клиенту (неверный пароль и т.п.), а не сбой: в лог не пишем
        $exceptions->dontReport(ApiException::class);

        $exceptions->render(static fn(ApiException $e): JsonResponse => $e->render());

        $exceptions->render(static fn(ValidationException $e): JsonResponse => ApiResponder::error(
            Translator::get('exceptions.validation'),
            422,
            $e->errors(),
        ));

        $exceptions->render(static fn(AuthenticationException $e): JsonResponse => ApiResponder::error(
            Translator::get('exceptions.unauthenticated'),
            401,
        ));

        $exceptions->render(static fn(ThrottleRequestsException $e): JsonResponse => ApiResponder::error(
            Translator::get('exceptions.too_many_requests'),
            429,
        ));

        $exceptions->render(static fn(NotFoundHttpException $e): JsonResponse => ApiResponder::error(
            Translator::get('exceptions.not_found'),
            404,
        ));

        $exceptions->render(static function (HttpExceptionInterface $e): JsonResponse {
            return ApiResponder::error($e->getMessage() !== '' ? $e->getMessage() : Translator::get('exceptions.http_error'), $e->getStatusCode());
        });
    })->create();
