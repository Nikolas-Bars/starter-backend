<?php

declare(strict_types=1);

namespace App\Modules\Chat\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="Chat.ChatMessageNotForwardableException",
 *     description="Служебное сообщение о звонке или в нём нечего пересылать: файлы ещё обрабатываются",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "Это сообщение нельзя переслать.", "data": null, "errors": {}}
 *     )
 * )
 */
final class ChatMessageNotForwardableException extends ApiException
{
    protected string $errorMessage = 'exceptions.chat.not_forwardable';

    protected int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY;
}
