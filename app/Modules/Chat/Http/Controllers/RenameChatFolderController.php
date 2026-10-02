<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Chat\Actions\RenameChatFolderAction;
use App\Modules\Chat\Http\Requests\ChatFolderRequest;
use App\Modules\Chat\Http\Resources\ChatFolderResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Patch;
use Spatie\RouteAttributes\Attributes\WhereNumber;

/**
 * @OA\Patch(
 *     path="/api/chat-folders/{folderId}",
 *     summary="Переименовать папку",
 *     tags={"Чаты"},
 *     security={{"sanctum": {}}},
 *     @OA\Parameter(name="folderId", in="path", required=true, @OA\Schema(type="integer", example=3)),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ChatFolderRequest")),
 *     @OA\Response(
 *         response=200,
 *         description="Папка",
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
 *     @OA\Response(response=404, ref="#/components/responses/Chat.ChatFolderNotFoundException"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError")
 * )
 */
final class RenameChatFolderController extends BaseController
{
    #[Patch('chat-folders/{folderId}', middleware: ['auth:sanctum', 'not_guest'])]
    #[WhereNumber('folderId')]
    public function __invoke(int $folderId, ChatFolderRequest $request, RenameChatFolderAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::ok(
            ChatFolderResource::make($action->run($user, $folderId, $request->name())),
            Translator::get('messages.chat_folder.updated'),
        );
    }
}
