<?php

declare(strict_types=1);

namespace App\Modules\Chat\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="Chat.ChatNotFoundException",
 *     description="Чата нет или пользователь в нём не участвует",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "Чат не найден.", "data": null, "errors": {}}
 *     )
 * )
 */
final class ChatNotFoundException extends ApiException
{
    protected string $errorMessage = 'exceptions.chat.not_found';

    protected int $statusCode = Response::HTTP_NOT_FOUND;
}
