<?php

declare(strict_types=1);

namespace App\Modules\User\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="User.InvalidAvatarException",
 *     description="Файл не открылся как картинка или слишком большой по пикселям",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "Не удалось прочитать картинку. Выберите другое фото.", "data": null, "errors": {}}
 *     )
 * )
 */
final class InvalidAvatarException extends ApiException
{
    protected string $errorMessage = 'exceptions.user.avatar_invalid';

    protected int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY;
}
