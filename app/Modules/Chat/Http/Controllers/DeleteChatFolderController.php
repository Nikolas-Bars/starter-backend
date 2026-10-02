<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Chat\Actions\DeleteChatFolderAction;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\WhereNumber;

/**
 * @OA\Delete(
 *     path="/api/chat-folders/{folderId}",
 *     summary="Удалить папку",
 *     description="Чаты остаются в общем списке — удаляется только папка.",
 *     tags={"Чаты"},
 *     security={{"sanctum": {}}},
 *     @OA\Parameter(name="folderId", in="path", required=true, @OA\Schema(type="integer", example=3)),
 *     @OA\Response(
 *         response=200,
 *         description="Папка удалена",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Папка удалена."),
 *             @OA\Property(property="data", type="object", nullable=true, example=null),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/User.GuestNotAllowedException"),
 *     @OA\Response(response=404, ref="#/components/responses/Chat.ChatFolderNotFoundException")
 * )
 */
final class DeleteChatFolderController extends BaseController
{
    #[Delete('chat-folders/{folderId}', middleware: ['auth:sanctum', 'not_guest'])]
    #[WhereNumber('folderId')]
    public function __invoke(int $folderId, Request $request, DeleteChatFolderAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->run($user, $folderId);

        return ApiResponder::ok(null, Translator::get('messages.chat_folder.deleted'));
    }
}
