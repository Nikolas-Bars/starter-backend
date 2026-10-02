<?php

declare(strict_types=1);

namespace App\Modules\Call\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Call\Actions\DeclineCallAction;
use App\Modules\Call\Http\Requests\DeclineCallRequest;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\WhereNumber;

/**
 * @OA\Post(
 *     path="/api/calls/{id}/decline",
 *     summary="Отклонить вызов из уведомления",
 *     description="Кнопка «Отклонить» в уведомлении о входящем звонке на телефоне: приложение может быть не запущено, поэтому вместо токена входа — ключ decline_token из push. Ключ подходит только к одному вызову. Звонящий сразу узнаёт, что вызов отклонён. Не более 30 запросов в минуту с одного IP.",
 *     tags={"Звонки"},
 *     security={},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=42)),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DeclineCallRequest")),
 *     @OA\Response(
 *         response=200,
 *         description="Вызов отклонён",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Вызов отклонён."),
 *             @OA\Property(property="data", type="object", nullable=true, example=null),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=404, ref="#/components/responses/Call.CallNotFoundException"),
 *     @OA\Response(response=409, ref="#/components/responses/Call.InvalidCallStateException"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
 *     @OA\Response(response=429, ref="#/components/responses/TooManyRequests")
 * )
 */
final class DeclineCallController extends BaseController
{
    #[Post('calls/{id}/decline', middleware: 'throttle:call-decline')]
    #[WhereNumber('id')]
    public function __invoke(int $id, DeclineCallRequest $request, DeclineCallAction $action): JsonResponse
    {
        $action->run($id, $request->token());

        return ApiResponder::ok(null, Translator::get('messages.call.declined'));
    }
}
