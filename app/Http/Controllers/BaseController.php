<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Starter API",
 *     description="API заготовки: регистрация, вход по Bearer-токену, профиль.",
 *     version="1.0.0",
 * )
 * @OA\Server(url=L5_SWAGGER_CONST_HOST, description="Локальная разработка")
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     description="Токен из ответа /api/auth/login или /api/auth/register"
 * )
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     description="Тело ответа при ошибке",
 *     @OA\Property(property="status", type="string", example="error"),
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(property="data", type="object", nullable=true, example=null),
 *     @OA\Property(property="errors", type="object", example={})
 * )
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     type="object",
 *     description="Положение страницы в списке",
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="last_page", type="integer", example=3),
 *     @OA\Property(property="per_page", type="integer", example=20),
 *     @OA\Property(property="total", type="integer", example=47)
 * )
 * @OA\Response(
 *     response="Unauthorized",
 *     description="Не авторизован",
 *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
 * )
 * @OA\Response(
 *     response="ValidationError",
 *     description="Ошибка валидации",
 *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
 * )
 * @OA\Response(
 *     response="TooManyRequests",
 *     description="Слишком много попыток",
 *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
 * )
 */
abstract class BaseController extends Controller
{
}
