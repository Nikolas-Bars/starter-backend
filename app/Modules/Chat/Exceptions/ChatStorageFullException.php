<?php

declare(strict_types=1);

namespace App\Modules\Chat\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="Chat.ChatStorageFullException",
 *     description="Общее место для файлов (10 ГБ) закончилось",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "Место для файлов закончилось. Попробуйте позже.", "data": null, "errors": {}}
 *     )
 * )
 */
final class ChatStorageFullException extends ApiException
{
    protected string $errorMessage = 'exceptions.chat.storage_full';

    protected int $statusCode = Response::HTTP_INSUFFICIENT_STORAGE;
}
