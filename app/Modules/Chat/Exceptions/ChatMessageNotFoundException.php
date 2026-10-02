<?php

declare(strict_types=1);

namespace App\Modules\Chat\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="Chat.ChatMessageNotFoundException",
 *     description="В этом чате нет такого сообщения",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "Сообщение не найдено.", "data": null, "errors": {}}
 *     )
 * )
 */
final class ChatMessageNotFoundException extends ApiException
{
    protected string $errorMessage = 'exceptions.chat.message_not_found';

    protected int $statusCode = Response::HTTP_NOT_FOUND;
}
