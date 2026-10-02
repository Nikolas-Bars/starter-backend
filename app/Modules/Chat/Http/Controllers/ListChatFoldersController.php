<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Chat\Actions\ListChatFoldersAction;
use App\Modules\Chat\Http\Resources\ChatFolderResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;

/**
 * @OA\Get(
 *     path="/api/chat-folders",
 *     summary="Папки чатов",
 *     description="Папки текущего пользователя в порядке создания. Их видит только владелец.",
 *     tags={"Чаты"},
 *     security={{"sanctum": {}}},
 *     @OA\Response(
 *         response=200,
 *         description="Папки",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Папки чатов."),
 *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ChatFolderResource")),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/User.GuestNotAllowedException")
 * )
 */
final class ListChatFoldersController extends BaseController
{
    #[Get('chat-folders', middleware: ['auth:sanctum', 'not_guest'])]
    public function __invoke(Request $request, ListChatFoldersAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::ok(ChatFolderResource::collection($action->run($user)), Translator::get('messages.chat_folder.list'));
    }
}
