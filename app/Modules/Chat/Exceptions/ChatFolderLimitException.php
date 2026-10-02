<?php

declare(strict_types=1);

namespace App\Modules\Chat\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="Chat.ChatFolderLimitException",
 *     description="Папок уже максимум",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "Папок может быть не больше 20.", "data": null, "errors": {}}
 *     )
 * )
 */
final class ChatFolderLimitException extends ApiException
{
    protected string $errorMessage = 'exceptions.chat.folder_limit';

    protected int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY;
}
