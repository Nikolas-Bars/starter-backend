<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Chat\Actions\CreateChatFolderAction;
use App\Modules\Chat\Http\Requests\ChatFolderRequest;
use App\Modules\Chat\Http\Resources\ChatFolderResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Post;

/**
 * @OA\Post(
 *     path="/api/chat-folders",
 *     summary="Создать папку",
 *     description="Пустая папка с этим названием. Не больше 20 папок; другие вкладки получают событие chat.folders.",
 *     tags={"Чаты"},
 *     security={{"sanctum": {}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ChatFolderRequest")),
 *     @OA\Response(
 *         response=201,
 *         description="Папка создана",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Папка создана."),
 *             @OA\Property(property="data", ref="#/components/schemas/ChatFolderResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/User.GuestNotAllowedException"),
 *     @OA\Response(response=422, ref="#/components/responses/Chat.ChatFolderLimitException")
 * )
 */
final class CreateChatFolderController extends BaseController
{
    #[Post('chat-folders', middleware: ['auth:sanctum', 'not_guest'])]
    public function __invoke(ChatFolderRequest $request, CreateChatFolderAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::created(
            ChatFolderResource::make($action->run($user, $request->name())),
            Translator::get('messages.chat_folder.created'),
        );
    }
}
