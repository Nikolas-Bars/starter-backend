<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Chat\Actions\AuthorizeChatFileAction;
use App\Modules\Chat\Http\Requests\AuthorizeChatFileRequest;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Get;

/**
 * @OA\Get(
 *     path="/api/attachments/authorize",
 *     summary="Проверить ссылку на файл (для Caddy)",
 *     description="Служебный: Caddy (forward_auth) спрашивает его перед тем, как отдать файл по /api/files/{path}. Исходная ссылка — в заголовке X-Forwarded-Uri. В ответ — заголовки X-File-Type и X-File-Disposition, с которыми Caddy отдаёт файл.",
 *     tags={"Чаты"},
 *     @OA\Parameter(name="X-Forwarded-Uri", in="header", required=true, @OA\Schema(type="string", example="/api/files/2026/10/abc.jpg?expires=1791072000&signature=…")),
 *     @OA\Response(
 *         response=200,
 *         description="Ссылка верна",
 *         @OA\Header(header="X-File-Type", @OA\Schema(type="string", example="image/jpeg")),
 *         @OA\Header(header="X-File-Disposition", @OA\Schema(type="string", example="inline; filename=photo.jpg")),
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Доступ к файлу разрешён."),
 *             @OA\Property(property="data", type="object", nullable=true, example=null),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=403, ref="#/components/responses/Chat.ChatFileForbiddenException")
 * )
 */
final class AuthorizeChatFileController extends BaseController
{
    #[Get('attachments/authorize')]
    public function __invoke(AuthorizeChatFileRequest $request, AuthorizeChatFileAction $action): JsonResponse
    {
        $access = $action->run($request->toDTO());

        return ApiResponder::ok(null, Translator::get('messages.chat.file_allowed'))->withHeaders([
            'X-File-Type'        => $access->content_type,
            'X-File-Disposition' => $access->content_disposition,
        ]);
    }
}
