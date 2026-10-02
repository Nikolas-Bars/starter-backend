<?php

declare(strict_types=1);

namespace App\Modules\Chat\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="Chat.ChatFolderNotFoundException",
 *     description="Папки нет или она чужая",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "Папка не найдена.", "data": null, "errors": {}}
 *     )
 * )
 */
final class ChatFolderNotFoundException extends ApiException
{
    protected string $errorMessage = 'exceptions.chat.folder_not_found';

    protected int $statusCode = Response::HTTP_NOT_FOUND;
}
