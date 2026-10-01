<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Auth\Actions\LogoutAction;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Post;

/**
 * @OA\Post(
 *     path="/api/auth/logout",
 *     summary="Выход",
 *     description="Отзывает токен текущего запроса",
 *     tags={"Авторизация"},
 *     security={{"sanctum": {}}},
 *     @OA\Response(
 *         response=200,
 *         description="Токен отозван",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Вы вышли из системы."),
 *             @OA\Property(property="data", type="object", nullable=true, example=null),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized")
 * )
 */
final class LogoutController extends BaseController
{
    #[Post('auth/logout', middleware: 'auth:sanctum')]
    public function __invoke(Request $request, LogoutAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->run($user);

        return ApiResponder::ok(null, Translator::get('messages.auth.logged_out'));
    }
}
