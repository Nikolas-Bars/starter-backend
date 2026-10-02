<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Chat\Actions\ListChatsAction;
use App\Modules\Chat\Http\Requests\ListChatsRequest;
use App\Modules\Chat\Http\Resources\ChatResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Get;

/**
 * @OA\Get(
 *     path="/api/chats",
 *     summary="Список чатов",
 *     description="Чаты текущего пользователя, где уже есть сообщения; сверху тот, где писали последним. По 30 на страницу. С folder_id — только чаты этой папки. Гостям недоступно.",
 *     tags={"Чаты"},
 *     security={{"sanctum": {}}},
 *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", minimum=1, example=1)),
 *     @OA\Parameter(ref="#/components/parameters/ListChatsRequest.folder_id"),
 *     @OA\Response(
 *         response=200,
 *         description="Страница списка",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Список чатов."),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/ChatResource")),
 *                 @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
 *             ),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/User.GuestNotAllowedException"),
 *     @OA\Response(response=404, ref="#/components/responses/Chat.ChatFolderNotFoundException"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError")
 * )
 */
final class ListChatsController extends BaseController
{
    private const PER_PAGE = 30;

    #[Get('chats', middleware: ['auth:sanctum', 'not_guest'])]
    public function __invoke(ListChatsRequest $request, ListChatsAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::paginated(
            $action->run($user, $request->folderId(), self::PER_PAGE),
            ChatResource::class,
            Translator::get('messages.chat.list'),
        );
    }
}
