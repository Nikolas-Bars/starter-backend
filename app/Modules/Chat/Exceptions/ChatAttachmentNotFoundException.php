<?php

declare(strict_types=1);

namespace App\Modules\Chat\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="Chat.ChatAttachmentNotFoundException",
 *     description="Файла нет, он чужой, уже отправлен или не обработался",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "Файл не найден или уже отправлен.", "data": null, "errors": {}}
 *     )
 * )
 */
final class ChatAttachmentNotFoundException extends ApiException
{
    protected string $errorMessage = 'exceptions.chat.attachment_not_found';

    protected int $statusCode = Response::HTTP_NOT_FOUND;
}
