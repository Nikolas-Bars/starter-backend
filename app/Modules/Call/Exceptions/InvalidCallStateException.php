<?php

declare(strict_types=1);

namespace App\Modules\Call\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="Call.InvalidCallStateException",
 *     description="Действие недоступно в текущем состоянии звонка (например, принять уже завершённый)",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "Звонок уже завершён или ещё не начался.", "data": null, "errors": {}}
 *     )
 * )
 */
final class InvalidCallStateException extends ApiException
{
    protected string $errorMessage = 'exceptions.call.invalid_state';

    protected int $statusCode = Response::HTTP_CONFLICT;
}
