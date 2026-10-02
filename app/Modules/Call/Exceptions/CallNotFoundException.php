<?php

declare(strict_types=1);

namespace App\Modules\Call\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="Call.CallNotFoundException",
 *     description="Звонок не найден",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "Звонок не найден.", "data": null, "errors": {}}
 *     )
 * )
 */
final class CallNotFoundException extends ApiException
{
    protected string $errorMessage = 'exceptions.call.not_found';

    protected int $statusCode = Response::HTTP_NOT_FOUND;
}
