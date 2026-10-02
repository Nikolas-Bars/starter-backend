<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Chat\Actions\OpenDirectChatAction;
use App\Modules\Chat\Http\Requests\OpenDirectChatRequest;
use App\Modules\Chat\Http\Resources\ChatResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Post;

/**
 * @OA\Post(
 *     path="/api/chats/direct",
 *     summary="Открыть личный чат",
 *     description="Личный чат с пользователем: существующий или новый. Новый чат появится в списке у обоих после первого сообщения.",
 *     tags={"Чаты"},
 *     security={{"sanctum": {}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/OpenDirectChatRequest")),
 *     @OA\Response(
 *         response=200,
 *         description="Чат",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Чат открыт."),
 *             @OA\Property(property="data", ref="#/components/schemas/ChatResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/User.GuestNotAllowedException"),
 *     @OA\Response(response=422, ref="#/components/responses/Chat.InvalidChatPeerException")
 * )
 */
final class OpenDirectChatController extends BaseController
{
    #[Post('chats/direct', middleware: ['auth:sanctum', 'not_guest'])]
    public function __invoke(OpenDirectChatRequest $request, OpenDirectChatAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::ok(ChatResource::make($action->run($user, $request->peerId())), Translator::get('messages.chat.opened'));
    }
}
