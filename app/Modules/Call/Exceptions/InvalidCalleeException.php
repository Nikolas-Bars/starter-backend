<?php

declare(strict_types=1);

namespace App\Modules\Call\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="Call.InvalidCalleeException",
 *     description="Собеседник не существует или это сам звонящий",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "Нельзя позвонить этому пользователю.", "data": null, "errors": {}}
 *     )
 * )
 */
final class InvalidCalleeException extends ApiException
{
    protected string $errorMessage = 'exceptions.call.invalid_callee';

    protected int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY;
}
