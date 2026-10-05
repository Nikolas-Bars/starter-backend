<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Chat\Actions\DeleteChatMessageAction;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\WhereNumber;

/**
 * @OA\Delete(
 *     path="/api/chats/{chatId}/messages/{messageId}",
 *     summary="Удалить своё сообщение",
 *     description="Сообщение удаляется у всех вместе с реакциями и файлами. Участники получают событие chat.message_deleted: chat_id, message_id, user_id (автор), last_changed и last_message — новое последнее сообщение чата, если удалено последнее. Служебные сообщения о звонках не удаляются.",
 *     tags={"Чаты"},
 *     security={{"sanctum": {}}},
 *     @OA\Parameter(name="chatId", in="path", required=true, @OA\Schema(type="integer", example=7)),
 *     @OA\Parameter(name="messageId", in="path", required=true, @OA\Schema(type="integer", example=42)),
 *     @OA\Response(
 *         response=200,
 *         description="Сообщение удалено",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Сообщение удалено."),
 *             @OA\Property(property="data", type="object", nullable=true, example=null),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/Chat.ChatMessageForbiddenException"),
 *     @OA\Response(response=404, ref="#/components/responses/Chat.ChatMessageNotFoundException")
 * )
 */
final class DeleteChatMessageController extends BaseController
{
    #[Delete('chats/{chatId}/messages/{messageId}', middleware: ['auth:sanctum', 'not_guest'])]
    #[WhereNumber('chatId')]
    #[WhereNumber('messageId')]
    public function __invoke(int $chatId, int $messageId, Request $request, DeleteChatMessageAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->run($user, $chatId, $messageId);

        return ApiResponder::ok(null, Translator::get('messages.chat.message_deleted'));
    }
}
