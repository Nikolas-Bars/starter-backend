<?php

declare(strict_types=1);

namespace App\Modules\Call\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="Call.NotCallParticipantException",
 *     description="Пользователь не участвует в звонке или не может выполнить это действие",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "Вы не участвуете в этом звонке.", "data": null, "errors": {}}
 *     )
 * )
 */
final class NotCallParticipantException extends ApiException
{
    protected string $errorMessage = 'exceptions.call.not_participant';

    protected int $statusCode = Response::HTTP_FORBIDDEN;
}
