<?php

declare(strict_types=1);

namespace App\Modules\Chat\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="Chat.InvalidChatPeerException",
 *     description="Собеседника нет, это сам пользователь или гость по ссылке для звонка",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "Написать этому пользователю нельзя.", "data": null, "errors": {}}
 *     )
 * )
 */
final class InvalidChatPeerException extends ApiException
{
    protected string $errorMessage = 'exceptions.chat.invalid_peer';

    protected int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY;
}
