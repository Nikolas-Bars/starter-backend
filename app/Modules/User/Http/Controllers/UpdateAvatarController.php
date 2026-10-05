<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\User\Actions\UpdateAvatarAction;
use App\Modules\User\Http\Requests\UpdateAvatarRequest;
use App\Modules\User\Http\Resources\UserResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Post;

/**
 * @OA\Post(
 *     path="/api/profile/avatar",
 *     summary="Поставить аватарку",
 *     description="Фото обрезается до квадрата по центру и сжимается в JPEG 512×512 без метаданных. Прежняя аватарка удаляется. Гостям недоступно.",
 *     tags={"Пользователи"},
 *     security={{"sanctum": {}}},
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(ref="#/components/schemas/UpdateAvatarRequest"))),
 *     @OA\Response(
 *         response=200,
 *         description="Аватарка обновлена",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Аватарка обновлена."),
 *             @OA\Property(property="data", ref="#/components/schemas/UserResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/User.GuestNotAllowedException"),
 *     @OA\Response(response=422, ref="#/components/responses/User.InvalidAvatarException")
 * )
 */
final class UpdateAvatarController extends BaseController
{
    #[Post('profile/avatar', middleware: ['auth:sanctum', 'not_guest', 'throttle:chat-attachment'])]
    public function __invoke(UpdateAvatarRequest $request, UpdateAvatarAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::ok(UserResource::make($action->run($user, $request->avatar()))->withEmail(), Translator::get('messages.user.avatar_updated'));
    }
}
