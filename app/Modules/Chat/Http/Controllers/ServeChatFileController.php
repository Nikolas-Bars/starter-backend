<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Chat\Actions\AuthorizeChatFileAction;
use App\Modules\Chat\Http\Requests\ServeChatFileRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Where;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @OA\Get(
 *     path="/api/files/{path}",
 *     summary="Скачать файл из чата",
 *     description="Ссылки берутся из url и thumb_url вложений. В проде файл отдаёт Caddy (с докачкой по Range), сюда запрос доходит только при локальной разработке.",
 *     tags={"Чаты"},
 *     @OA\Parameter(name="path", in="path", required=true, @OA\Schema(type="string", example="2026/10/abc.jpg")),
 *     @OA\Parameter(name="expires", in="query", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="signature", in="query", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="Файл", @OA\MediaType(mediaType="application/octet-stream")),
 *     @OA\Response(response=403, ref="#/components/responses/Chat.ChatFileForbiddenException")
 * )
 */
final class ServeChatFileController extends BaseController
{
    #[Get('files/{path}')]
    #[Where('path', '.+')]
    public function __invoke(ServeChatFileRequest $request, AuthorizeChatFileAction $action): BinaryFileResponse
    {
        $access = $action->run($request->toDTO());

        return new BinaryFileResponse(
            Storage::disk(Config::string('attachments.disk'))->path($access->path),
            headers: $access->headers(),
            autoEtag: true,
        );
    }
}
