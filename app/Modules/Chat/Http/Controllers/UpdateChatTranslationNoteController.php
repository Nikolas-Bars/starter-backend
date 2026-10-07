<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Chat\Actions\UpdateChatTranslationNoteAction;
use App\Modules\Chat\Http\Requests\ChatTranslationNoteRequest;
use App\Modules\Chat\Http\Resources\ChatResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Put;
use Spatie\RouteAttributes\Attributes\WhereNumber;

/**
 * @OA\Put(
 *     path="/api/chats/{chatId}/translation-note",
 *     summary="Заметка для переводчика",
 *     description="Кто кем друг другу приходится («бабушка и внук», «коллеги»): по ней автоперевод выбирает обращения. Общая на чат, менять может любой участник; остальные получают событие chat.translation_note.",
 *     tags={"Чаты"},
 *     security={{"sanctum": {}}},
 *     @OA\Parameter(name="chatId", in="path", required=true, @OA\Schema(type="integer", example=7)),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ChatTranslationNoteRequest")),
 *     @OA\Response(
 *         response=200,
 *         description="Чат",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Заметка для перевода сохранена."),
 *             @OA\Property(property="data", ref="#/components/schemas/ChatResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/User.GuestNotAllowedException"),
 *     @OA\Response(response=404, ref="#/components/responses/Chat.ChatNotFoundException"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError")
 * )
 */
final class UpdateChatTranslationNoteController extends BaseController
{
    #[Put('chats/{chatId}/translation-note', middleware: ['auth:sanctum', 'not_guest'])]
    #[WhereNumber('chatId')]
    public function __invoke(int $chatId, ChatTranslationNoteRequest $request, UpdateChatTranslationNoteAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::ok(
            ChatResource::make($action->run($user, $chatId, $request->note())),
            Translator::get('messages.chat.translation_note_updated'),
        );
    }
}
