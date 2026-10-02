<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\User\Actions\UpdateProfileAction;
use App\Modules\User\Http\Requests\UpdateProfileRequest;
use App\Modules\User\Http\Resources\UserResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Patch;

/**
 * @OA\Patch(
 *     path="/api/profile",
 *     summary="Изменить профиль",
 *     description="Имя и ник текущего пользователя. По нику (и email) пользователя находят в поиске. Гостям недоступно.",
 *     tags={"Пользователи"},
 *     security={{"sanctum": {}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/UpdateProfileRequest")),
 *     @OA\Response(
 *         response=200,
 *         description="Профиль сохранён",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Профиль сохранён."),
 *             @OA\Property(property="data", ref="#/components/schemas/UserResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/User.GuestNotAllowedException"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError")
 * )
 */
final class UpdateProfileController extends BaseController
{
    #[Patch('profile', middleware: ['auth:sanctum', 'not_guest'])]
    public function __invoke(UpdateProfileRequest $request, UpdateProfileAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::ok(UserResource::make($action->run($user, $request->toDTO()))->withEmail(), Translator::get('messages.user.profile_updated'));
    }
}
