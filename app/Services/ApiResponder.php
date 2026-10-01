<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Единый формат ответов API:
 * { "status": "success|error", "message": string, "data": mixed, "errors": object }
 */
final class ApiResponder
{
    public static function ok(mixed $data = null, string $message = 'OK'): JsonResponse
    {
        return self::build('success', $message, $data, [], Response::HTTP_OK);
    }

    public static function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return self::build('success', $message, $data, [], Response::HTTP_CREATED);
    }

    public static function noContent(): JsonResponse
    {
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<string, mixed> $errors
     */
    public static function error(
        string $message,
        int    $code = Response::HTTP_BAD_REQUEST,
        array  $errors = [],
    ): JsonResponse {
        return self::build('error', $message, null, $errors, $code);
    }

    /**
     * @param array<string, mixed> $errors
     */
    private static function build(
        string $status,
        string $message,
        mixed  $data,
        array  $errors,
        int    $code,
    ): JsonResponse {
        return new JsonResponse([
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
            'errors'  => (object)$errors,
        ], $code, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
