<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Chat\Actions\ListChatMessagesAction;
use App\Modules\Chat\Http\Requests\ListChatMessagesRequest;
use App\Modules\Chat\Http\Resources\ChatMessageResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\WhereNumber;

/**
 * @OA\Get(
 *     path="/api/chats/{chatId}/messages",
 *     summary="Сообщения чата",
 *     description="До 50 сообщений от старых к новым: без before_id — самые новые, с ним — те, что старше (листание вверх).",
 *     tags={"Чаты"},
 *     security={{"sanctum": {}}},
 *     @OA\Parameter(name="chatId", in="path", required=true, @OA\Schema(type="integer", example=7)),
 *     @OA\Parameter(ref="#/components/parameters/ListChatMessagesRequest.before_id"),
 *     @OA\Response(
 *         response=200,
 *         description="Страница переписки",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Сообщения чата."),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/ChatMessageResource")),
 *                 @OA\Property(property="has_more", type="boolean", description="Есть ли сообщения старше первого из items")
 *             ),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/User.GuestNotAllowedException"),
 *     @OA\Response(response=404, ref="#/components/responses/Chat.ChatNotFoundException"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError")
 * )
 */
final class ListChatMessagesController extends BaseController
{
    #[Get('chats/{chatId}/messages', middleware: ['auth:sanctum', 'not_guest'])]
    #[WhereNumber('chatId')]
    public function __invoke(int $chatId, ListChatMessagesRequest $request, ListChatMessagesAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $page = $action->run($user, $chatId, $request->toDTO());

        return ApiResponder::ok([
            'items'    => ChatMessageResource::collection($page->items),
            'has_more' => $page->has_more,
        ], Translator::get('messages.chat.messages'));
    }
}
