<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Chat\Actions\UploadChatAttachmentAction;
use App\Modules\Chat\Http\Requests\UploadChatAttachmentRequest;
use App\Modules\Chat\Http\Resources\ChatAttachmentResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Post;

/**
 * @OA\Post(
 *     path="/api/attachments",
 *     summary="Загрузить файл для сообщения",
 *     description="Файл до 50 МБ. Тип определяется по содержимому: фото (JPEG, PNG, WebP, HEIC, GIF) сжимаются и теряют метаданные с координатами, видео сжимается до 720p, голосовое (voice=true) — в m4a; остальное хранится как есть. Пока идёт сжатие, status = processing — сообщение с файлом можно отправлять сразу, готовый файл придёт событием chat.attachment. Не отправленный за сутки файл удаляется. Не больше 30 файлов в минуту.",
 *     tags={"Чаты"},
 *     security={{"sanctum": {}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(ref="#/components/schemas/UploadChatAttachmentRequest"))
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Файл принят",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Файл загружен."),
 *             @OA\Property(property="data", ref="#/components/schemas/ChatAttachmentResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/User.GuestNotAllowedException"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
 *     @OA\Response(response=429, ref="#/components/responses/TooManyRequests"),
 *     @OA\Response(response=507, ref="#/components/responses/Chat.ChatStorageFullException")
 * )
 */
final class UploadChatAttachmentController extends BaseController
{
    #[Post('attachments', middleware: ['auth:sanctum', 'not_guest', 'throttle:chat-attachment'])]
    public function __invoke(UploadChatAttachmentRequest $request, UploadChatAttachmentAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::created(
            ChatAttachmentResource::make($action->run($user, $request->toDTO())),
            Translator::get('messages.chat.file_uploaded'),
        );
    }
}
