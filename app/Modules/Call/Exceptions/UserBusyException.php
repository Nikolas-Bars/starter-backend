<?php

declare(strict_types=1);

namespace App\Modules\Call\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="Call.UserBusyException",
 *     description="Пользователь уже участвует в другом звонке",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "Сначала завершите текущий звонок.", "data": null, "errors": {}}
 *     )
 * )
 */
final class UserBusyException extends ApiException
{
    protected string $errorMessage = 'exceptions.call.already_in_call';

    protected int $statusCode = Response::HTTP_CONFLICT;
}
