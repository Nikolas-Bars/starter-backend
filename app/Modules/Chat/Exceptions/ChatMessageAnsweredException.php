<?php

declare(strict_types=1);

namespace App\Modules\Chat\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="Chat.ChatMessageAnsweredException",
 *     description="После сообщения в чате уже писал собеседник",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "На сообщение уже ответили — изменить его нельзя.", "data": null, "errors": {}}
 *     )
 * )
 */
final class ChatMessageAnsweredException extends ApiException
{
    protected string $errorMessage = 'exceptions.chat.answered';

    protected int $statusCode = Response::HTTP_CONFLICT;
}
