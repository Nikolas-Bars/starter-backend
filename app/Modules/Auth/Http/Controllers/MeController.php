<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\User\Http\Resources\UserResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;

/**
 * @OA\Get(
 *     path="/api/auth/me",
 *     summary="Текущий пользователь",
 *     tags={"Авторизация"},
 *     security={{"sanctum": {}}},
 *     @OA\Response(
 *         response=200,
 *         description="Профиль пользователя, которому принадлежит токен",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Текущий пользователь."),
 *             @OA\Property(property="data", ref="#/components/schemas/UserResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized")
 * )
 */
final class MeController extends BaseController
{
    #[Get('auth/me', middleware: 'auth:sanctum')]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::ok(UserResource::make($user), Translator::get('messages.auth.me'));
    }
}
