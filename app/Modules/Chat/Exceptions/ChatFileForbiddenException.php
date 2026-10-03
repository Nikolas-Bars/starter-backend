<?php

declare(strict_types=1);

namespace App\Modules\Chat\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="Chat.ChatFileForbiddenException",
 *     description="Подпись ссылки неверна или срок вышел, либо файла уже нет",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "Ссылка на файл устарела или неверна.", "data": null, "errors": {}}
 *     )
 * )
 */
final class ChatFileForbiddenException extends ApiException
{
    protected string $errorMessage = 'exceptions.chat.file_forbidden';

    protected int $statusCode = Response::HTTP_FORBIDDEN;
}
