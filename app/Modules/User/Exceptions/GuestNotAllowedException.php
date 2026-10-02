<?php

declare(strict_types=1);

namespace App\Modules\User\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="User.GuestNotAllowedException",
 *     description="Гостю по ссылке для звонка это недоступно",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "Гостям это недоступно. Зарегистрируйтесь, чтобы продолжить.", "data": null, "errors": {}}
 *     )
 * )
 */
final class GuestNotAllowedException extends ApiException
{
    protected string $errorMessage = 'exceptions.user.guest_forbidden';

    protected int $statusCode = Response::HTTP_FORBIDDEN;
}
