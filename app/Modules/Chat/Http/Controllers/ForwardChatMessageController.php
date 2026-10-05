<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Chat\Actions\ForwardChatMessageAction;
use App\Modules\Chat\Http\Requests\ForwardChatMessageRequest;
use App\Modules\Chat\Http\Resources\ChatMessageResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\WhereNumber;

/**
 * @OA\Post(
 *     path="/api/chats/{chatId}/messages/forward",
 *     summary="Переслать сообщение",
 *     description="Копирует сообщение из любого своего чата в этот: текст и готовые файлы (у копии свои файлы на диске). В ответе и в событии chat.message у сообщения заполнено forwarded_from — автор оригинала; при пересылке пересланного остаётся первый автор. Сообщения о звонках не пересылаются. Повтор с тем же client_id возвращает уже сохранённое.",
 *     tags={"Чаты"},
 *     security={{"sanctum": {}}},
 *     @OA\Parameter(name="chatId", in="path", required=true, @OA\Schema(type="integer", example=7), description="Куда переслать"),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ForwardChatMessageRequest")),
 *     @OA\Response(
 *         response=201,
 *         description="Сообщение переслано",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Сообщение переслано."),
 *             @OA\Property(property="data", ref="#/components/schemas/ChatMessageResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/User.GuestNotAllowedException"),
 *     @OA\Response(response=404, ref="#/components/responses/Chat.ChatMessageNotFoundException"),
 *     @OA\Response(response=422, ref="#/components/responses/Chat.ChatMessageNotForwardableException"),
 *     @OA\Response(response=429, ref="#/components/responses/TooManyRequests"),
 *     @OA\Response(response=507, ref="#/components/responses/Chat.ChatStorageFullException")
 * )
 */
final class ForwardChatMessageController extends BaseController
{
    #[Post('chats/{chatId}/messages/forward', middleware: ['auth:sanctum', 'not_guest', 'throttle:chat-message'])]
    #[WhereNumber('chatId')]
    public function __invoke(int $chatId, ForwardChatMessageRequest $request, ForwardChatMessageAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::created(
            ChatMessageResource::make($action->run($user, $chatId, $request->toDTO())),
            Translator::get('messages.chat.forwarded'),
        );
    }
}
