<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Chat\Actions\MarkChatReadAction;
use App\Modules\Chat\Http\Requests\MarkChatReadRequest;
use App\Modules\Chat\Http\Resources\ChatReadStateResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\WhereNumber;

/**
 * @OA\Post(
 *     path="/api/chats/{chatId}/read",
 *     summary="Отметить прочитанным",
 *     description="Всё до message_id включительно прочитано. Отметка только сдвигается вперёд; если сдвинулась, участники получают событие chat.read.",
 *     tags={"Чаты"},
 *     security={{"sanctum": {}}},
 *     @OA\Parameter(name="chatId", in="path", required=true, @OA\Schema(type="integer", example=7)),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/MarkChatReadRequest")),
 *     @OA\Response(
 *         response=200,
 *         description="Отметка прочитанного",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Сообщения прочитаны."),
 *             @OA\Property(property="data", ref="#/components/schemas/ChatReadStateResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/User.GuestNotAllowedException"),
 *     @OA\Response(response=404, ref="#/components/responses/Chat.ChatNotFoundException"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError")
 * )
 */
final class MarkChatReadController extends BaseController
{
    #[Post('chats/{chatId}/read', middleware: ['auth:sanctum', 'not_guest'])]
    #[WhereNumber('chatId')]
    public function __invoke(int $chatId, MarkChatReadRequest $request, MarkChatReadAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::ok(
            ChatReadStateResource::make($action->run($user, $chatId, $request->messageId())),
            Translator::get('messages.chat.read'),
        );
    }
}
