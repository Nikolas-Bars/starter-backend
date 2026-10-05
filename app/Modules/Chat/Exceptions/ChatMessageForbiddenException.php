<?php

declare(strict_types=1);

namespace App\Modules\Chat\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="Chat.ChatMessageForbiddenException",
 *     description="Сообщение чужое или служебное (звонок)",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "Удалить можно только своё сообщение.", "data": null, "errors": {}}
 *     )
 * )
 */
final class ChatMessageForbiddenException extends ApiException
{
    protected string $errorMessage = 'exceptions.chat.message_forbidden';

    protected int $statusCode = Response::HTTP_FORBIDDEN;
}
