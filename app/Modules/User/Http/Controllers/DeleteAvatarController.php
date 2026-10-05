<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\User\Actions\DeleteAvatarAction;
use App\Modules\User\Http\Resources\UserResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Delete;

/**
 * @OA\Delete(
 *     path="/api/profile/avatar",
 *     summary="Убрать аватарку",
 *     description="Файл аватарки удаляется, вместо неё снова показываются инициалы.",
 *     tags={"Пользователи"},
 *     security={{"sanctum": {}}},
 *     @OA\Response(
 *         response=200,
 *         description="Аватарка удалена",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Аватарка удалена."),
 *             @OA\Property(property="data", ref="#/components/schemas/UserResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/User.GuestNotAllowedException")
 * )
 */
final class DeleteAvatarController extends BaseController
{
    #[Delete('profile/avatar', middleware: ['auth:sanctum', 'not_guest'])]
    public function __invoke(Request $request, DeleteAvatarAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::ok(UserResource::make($action->run($user))->withEmail(), Translator::get('messages.user.avatar_deleted'));
    }
}
