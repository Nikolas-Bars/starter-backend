<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Auth\Actions\RegisterAction;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Http\Resources\AuthTokenResource;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Post;

/**
 * @OA\Post(
 *     path="/api/auth/register",
 *     summary="Регистрация",
 *     description="Создаёт пользователя и сразу выдаёт токен доступа",
 *     tags={"Авторизация"},
 *     security={},
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/RegisterRequest")),
 *     @OA\Response(
 *         response=201,
 *         description="Пользователь зарегистрирован",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Регистрация прошла успешно."),
 *             @OA\Property(property="data", ref="#/components/schemas/AuthTokenResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
 *     @OA\Response(response=429, ref="#/components/responses/TooManyRequests")
 * )
 */
final class RegisterController extends BaseController
{
    #[Post('auth/register', middleware: 'throttle:register')]
    public function __invoke(RegisterRequest $request, RegisterAction $action): JsonResponse
    {
        $token = $action->run($request->toDTO(), $request->deviceName());

        return ApiResponder::created(AuthTokenResource::make($token), Translator::get('messages.auth.registered'));
    }
}
