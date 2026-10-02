<?php

declare(strict_types=1);

namespace App\Modules\Call\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Call\Actions\IssueWebSocketTicketAction;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Post;

/**
 * @OA\Post(
 *     path="/api/calls/ws-ticket",
 *     summary="Билет на подключение к серверу звонков",
 *     description="Одноразовый билет для WebSocket: ws://…?ticket=… Действует несколько секунд, после подключения сгорает. Токен доступа в адрес сокета не передаётся.",
 *     tags={"Звонки"},
 *     security={{"sanctum": {}}},
 *     @OA\Response(
 *         response=201,
 *         description="Билет выдан",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Билет на подключение выдан."),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="ticket", type="string", example="Qm9vb2JvYm9i..."),
 *                 @OA\Property(property="expires_in", type="integer", example=30, description="Секунд до истечения")
 *             ),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized")
 * )
 */
final class IssueWebSocketTicketController extends BaseController
{
    #[Post('calls/ws-ticket', middleware: 'auth:sanctum')]
    public function __invoke(Request $request, IssueWebSocketTicketAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::created($action->run($user), Translator::get('messages.call.ws_ticket'));
    }
}
