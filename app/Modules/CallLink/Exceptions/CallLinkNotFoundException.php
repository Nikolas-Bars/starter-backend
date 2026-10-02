<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Exceptions;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Response(
 *     response="CallLink.CallLinkNotFoundException",
 *     description="Ссылки нет или её перевыпустили",
 *     @OA\JsonContent(
 *         allOf={@OA\Schema(ref="#/components/schemas/ErrorResponse")},
 *         example={"status": "error", "message": "Ссылка для звонка недействительна. Попросите прислать новую.", "data": null, "errors": {}}
 *     )
 * )
 */
final class CallLinkNotFoundException extends ApiException
{
    protected string $errorMessage = 'exceptions.call_link.not_found';

    protected int $statusCode = Response::HTTP_NOT_FOUND;
}
