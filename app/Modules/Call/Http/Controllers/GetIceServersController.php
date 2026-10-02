<?php

declare(strict_types=1);

namespace App\Modules\Call\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Call\Actions\GetIceServersAction;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;

/**
 * @OA\Get(
 *     path="/api/calls/ice-servers",
 *     summary="ICE-серверы для звонков",
 *     description="Список STUN/TURN-серверов в формате RTCConfiguration.iceServers: браузер использует их, чтобы установить прямое соединение. Если задан CALL_TURN_SECRET, логин TURN временный и выдаётся каждому пользователю свой — запрашивайте список перед каждым звонком.",
 *     tags={"Звонки"},
 *     security={{"sanctum": {}}},
 *     @OA\Response(
 *         response=200,
 *         description="Настройки соединения",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Настройки соединения для звонков."),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(
 *                     property="ice_servers",
 *                     type="array",
 *                     @OA\Items(
 *                         type="object",
 *                         @OA\Property(property="urls", type="array", @OA\Items(type="string"), example={"stun:stun.l.google.com:19302"}),
 *                         @OA\Property(property="username", type="string", nullable=true),
 *                         @OA\Property(property="credential", type="string", nullable=true)
 *                     )
 *                 )
 *             ),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized")
 * )
 */
final class GetIceServersController extends BaseController
{
    #[Get('calls/ice-servers', middleware: 'auth:sanctum')]
    public function __invoke(Request $request, GetIceServersAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::ok(['ice_servers' => $action->run($user)], Translator::get('messages.call.ice_servers'));
    }
}
