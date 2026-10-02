<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Chat\Actions\SetChatInFolderAction;
use App\Modules\Chat\Http\Resources\ChatFolderResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Put;
use Spatie\RouteAttributes\Attributes\WhereNumber;

/**
 * @OA\Put(
 *     path="/api/chat-folders/{folderId}/chats/{chatId}",
 *     summary="Добавить чат в папку",
 *     description="Повтор ничего не меняет. Один чат может быть в нескольких папках.",
 *     tags={"Чаты"},
 *     security={{"sanctum": {}}},
 *     @OA\Parameter(name="folderId", in="path", required=true, @OA\Schema(type="integer", example=3)),
 *     @OA\Parameter(name="chatId", in="path", required=true, @OA\Schema(type="integer", example=7)),
 *     @OA\Response(
 *         response=200,
 *         description="Папка в новом состоянии",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Папка сохранена."),
 *             @OA\Property(property="data", ref="#/components/schemas/ChatFolderResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/User.GuestNotAllowedException"),
 *     @OA\Response(response=404, ref="#/components/responses/Chat.ChatFolderNotFoundException")
 * )
 */
final class AddChatToFolderController extends BaseController
{
    #[Put('chat-folders/{folderId}/chats/{chatId}', middleware: ['auth:sanctum', 'not_guest'])]
    #[WhereNumber('folderId')]
    #[WhereNumber('chatId')]
    public function __invoke(int $folderId, int $chatId, Request $request, SetChatInFolderAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::ok(
            ChatFolderResource::make($action->run($user, $folderId, $chatId, true)),
            Translator::get('messages.chat_folder.updated'),
        );
    }
}
