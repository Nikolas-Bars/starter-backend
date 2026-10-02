<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Chat\Actions\ReactToMessageAction;
use App\Modules\Chat\Http\Resources\ChatMessageResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\WhereNumber;

/**
 * @OA\Delete(
 *     path="/api/chats/{chatId}/messages/{messageId}/reaction",
 *     summary="Убрать свою реакцию",
 *     description="Если реакция была, участники получают событие chat.reaction. Без реакции — ничего не меняет.",
 *     tags={"Чаты"},
 *     security={{"sanctum": {}}},
 *     @OA\Parameter(name="chatId", in="path", required=true, @OA\Schema(type="integer", example=7)),
 *     @OA\Parameter(name="messageId", in="path", required=true, @OA\Schema(type="integer", example=42)),
 *     @OA\Response(
 *         response=200,
 *         description="Сообщение с реакциями",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Реакция убрана."),
 *             @OA\Property(property="data", ref="#/components/schemas/ChatMessageResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/User.GuestNotAllowedException"),
 *     @OA\Response(response=404, ref="#/components/responses/Chat.ChatMessageNotFoundException"),
 *     @OA\Response(response=429, ref="#/components/responses/TooManyRequests")
 * )
 */
final class RemoveMessageReactionController extends BaseController
{
    #[Delete('chats/{chatId}/messages/{messageId}/reaction', middleware: ['auth:sanctum', 'not_guest', 'throttle:chat-reaction'])]
    #[WhereNumber('chatId')]
    #[WhereNumber('messageId')]
    public function __invoke(int $chatId, int $messageId, Request $request, ReactToMessageAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::ok(
            ChatMessageResource::make($action->run($user, $chatId, $messageId, null)),
            Translator::get('messages.chat.reaction_removed'),
        );
    }
}
