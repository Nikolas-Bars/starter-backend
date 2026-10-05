<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Chat\Actions\EditChatMessageAction;
use App\Modules\Chat\Http\Requests\EditChatMessageRequest;
use App\Modules\Chat\Http\Resources\ChatMessageResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Patch;
use Spatie\RouteAttributes\Attributes\WhereNumber;

/**
 * @OA\Patch(
 *     path="/api/chats/{chatId}/messages/{messageId}",
 *     summary="Изменить своё сообщение",
 *     description="Меняет текст своего сообщения, пока на него не ответили: после него в чате нет сообщений и звонков от других участников. Пересланные сообщения и сообщения о звонках не меняются. Участники получают событие chat.message_updated: chat_id и message целиком, с edited_at.",
 *     tags={"Чаты"},
 *     security={{"sanctum": {}}},
 *     @OA\Parameter(name="chatId", in="path", required=true, @OA\Schema(type="integer", example=7)),
 *     @OA\Parameter(name="messageId", in="path", required=true, @OA\Schema(type="integer", example=42)),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/EditChatMessageRequest")),
 *     @OA\Response(
 *         response=200,
 *         description="Сообщение изменено",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Сообщение изменено."),
 *             @OA\Property(property="data", ref="#/components/schemas/ChatMessageResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/Chat.ChatMessageNotEditableException"),
 *     @OA\Response(response=404, ref="#/components/responses/Chat.ChatMessageNotFoundException"),
 *     @OA\Response(response=409, ref="#/components/responses/Chat.ChatMessageAnsweredException"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError")
 * )
 */
final class EditChatMessageController extends BaseController
{
    #[Patch('chats/{chatId}/messages/{messageId}', middleware: ['auth:sanctum', 'not_guest', 'throttle:chat-message'])]
    #[WhereNumber('chatId')]
    #[WhereNumber('messageId')]
    public function __invoke(int $chatId, int $messageId, EditChatMessageRequest $request, EditChatMessageAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::ok(
            ChatMessageResource::make($action->run($user, $chatId, $messageId, $request->body())),
            Translator::get('messages.chat.edited'),
        );
    }
}
