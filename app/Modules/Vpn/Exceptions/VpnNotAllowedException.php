<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="Vpn.VpnNotAllowedException",
 *     description="Пользователю не выдан доступ к VPN: клиент прячет раздел VPN",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "VPN вам недоступен.", "data": null, "errors": {}}
 *     )
 * )
 */
final class VpnNotAllowedException extends ApiException
{
    protected string $errorMessage = 'exceptions.vpn.not_allowed';

    protected int $statusCode = Response::HTTP_FORBIDDEN;
}
