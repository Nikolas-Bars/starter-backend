<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\User\Actions\UpdateLocaleAction;
use App\Modules\User\Http\Requests\UpdateLocaleRequest;
use App\Modules\User\Http\Resources\UserResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Put;

/**
 * @OA\Put(
 *     path="/api/profile/locale",
 *     summary="Сменить язык интерфейса",
 *     description="Язык хранится у пользователя: на него переводится интерфейс на всех устройствах и входящие сообщения. Доступно и гостям. Сообщение ответа — уже на новом языке.",
 *     tags={"Пользователи"},
 *     security={{"sanctum": {}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/UpdateLocaleRequest")),
 *     @OA\Response(
 *         response=200,
 *         description="Язык сохранён",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Đã lưu ngôn ngữ."),
 *             @OA\Property(property="data", ref="#/components/schemas/UserResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError")
 * )
 */
final class UpdateLocaleController extends BaseController
{
    #[Put('profile/locale', middleware: ['auth:sanctum'])]
    public function __invoke(UpdateLocaleRequest $request, UpdateLocaleAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::ok(UserResource::make($action->run($user, $request->locale()))->withEmail(), Translator::get('messages.user.locale_updated'));
    }
}
