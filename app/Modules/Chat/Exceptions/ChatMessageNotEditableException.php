<?php

declare(strict_types=1);

namespace App\Modules\Chat\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="Chat.ChatMessageNotEditableException",
 *     description="Чужое, пересланное или служебное сообщение о звонке",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "Изменить можно только своё сообщение.", "data": null, "errors": {}}
 *     )
 * )
 */
final class ChatMessageNotEditableException extends ApiException
{
    protected string $errorMessage = 'exceptions.chat.not_editable';

    protected int $statusCode = Response::HTTP_FORBIDDEN;
}
