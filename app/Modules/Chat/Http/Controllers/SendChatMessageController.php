<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Chat\Actions\SendChatMessageAction;
use App\Modules\Chat\Http\Requests\SendChatMessageRequest;
use App\Modules\Chat\Http\Resources\ChatMessageResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\WhereNumber;

/**
 * @OA\Post(
 *     path="/api/chats/{chatId}/messages",
 *     summary="Отправить сообщение",
 *     description="Сохраняет сообщение; участники чата, что в сети, получают его по WebSocket событием chat.message. Повтор с тем же client_id возвращает уже сохранённое сообщение. Не больше 60 сообщений в минуту.",
 *     tags={"Чаты"},
 *     security={{"sanctum": {}}},
 *     @OA\Parameter(name="chatId", in="path", required=true, @OA\Schema(type="integer", example=7)),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/SendChatMessageRequest")),
 *     @OA\Response(
 *         response=201,
 *         description="Сообщение сохранено",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Сообщение отправлено."),
 *             @OA\Property(property="data", ref="#/components/schemas/ChatMessageResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/User.GuestNotAllowedException"),
 *     @OA\Response(response=404, ref="#/components/responses/Chat.ChatNotFoundException"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
 *     @OA\Response(response=429, ref="#/components/responses/TooManyRequests")
 * )
 */
final class SendChatMessageController extends BaseController
{
    #[Post('chats/{chatId}/messages', middleware: ['auth:sanctum', 'not_guest', 'throttle:chat-message'])]
    #[WhereNumber('chatId')]
    public function __invoke(int $chatId, SendChatMessageRequest $request, SendChatMessageAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::created(
            ChatMessageResource::make($action->run($user, $chatId, $request->toDTO())),
            Translator::get('messages.chat.sent'),
        );
    }
}
