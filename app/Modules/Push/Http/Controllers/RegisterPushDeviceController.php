<?php

declare(strict_types=1);

namespace App\Modules\Push\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Push\Actions\RegisterPushDeviceAction;
use App\Modules\Push\Http\Requests\RegisterPushDeviceRequest;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Put;

/**
 * @OA\Put(
 *     path="/api/push/devices",
 *     summary="Зарегистрировать телефон для push",
 *     description="Мобильное приложение присылает токен Firebase Cloud Messaging после входа и при каждом запуске. Через push телефон узнаёт о входящем звонке, даже когда приложение закрыто. Устройство привязано к токену входа: выход из аккаунта его удаляет. Если на телефоне вошёл другой пользователь, устройство переходит к нему. Гостям недоступно.",
 *     tags={"Push"},
 *     security={{"sanctum": {}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/RegisterPushDeviceRequest")),
 *     @OA\Response(
 *         response=200,
 *         description="Устройство зарегистрировано",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Уведомления включены."),
 *             @OA\Property(property="data", type="object", nullable=true, example=null),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/User.GuestNotAllowedException"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError")
 * )
 */
final class RegisterPushDeviceController extends BaseController
{
    #[Put('push/devices', middleware: ['auth:sanctum', 'not_guest'])]
    public function __invoke(RegisterPushDeviceRequest $request, RegisterPushDeviceAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->run($user, $request->token());

        return ApiResponder::ok(null, Translator::get('messages.push.registered'));
    }
}
