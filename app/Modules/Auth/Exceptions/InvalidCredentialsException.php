<?php

declare(strict_types=1);

namespace App\Modules\Auth\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="Auth.InvalidCredentialsException",
 *     description="Неверный email или пароль",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "Неверный email или пароль.", "data": null, "errors": {}}
 *     )
 * )
 */
final class InvalidCredentialsException extends ApiException
{
    protected string $errorMessage = 'exceptions.auth.invalid_credentials';

    protected int $statusCode = Response::HTTP_UNAUTHORIZED;
}
