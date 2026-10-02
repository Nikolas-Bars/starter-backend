<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Chat\Actions\ShowChatAction;
use App\Modules\Chat\Http\Resources\ChatResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\WhereNumber;

/**
 * @OA\Get(
 *     path="/api/chats/{chatId}",
 *     summary="Чат",
 *     description="Один чат текущего пользователя — например, когда пришло сообщение из чата, которого ещё нет в списке.",
 *     tags={"Чаты"},
 *     security={{"sanctum": {}}},
 *     @OA\Parameter(name="chatId", in="path", required=true, @OA\Schema(type="integer", example=7)),
 *     @OA\Response(
 *         response=200,
 *         description="Чат",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Чат."),
 *             @OA\Property(property="data", ref="#/components/schemas/ChatResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/User.GuestNotAllowedException"),
 *     @OA\Response(response=404, ref="#/components/responses/Chat.ChatNotFoundException")
 * )
 */
final class ShowChatController extends BaseController
{
    #[Get('chats/{chatId}', middleware: ['auth:sanctum', 'not_guest'])]
    #[WhereNumber('chatId')]
    public function __invoke(int $chatId, Request $request, ShowChatAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::ok(ChatResource::make($action->run($user, $chatId)), Translator::get('messages.chat.show'));
    }
}
