<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\CallLink\Actions\ShowCallLinkAction;
use App\Modules\CallLink\Http\Resources\CallLinkInviteResource;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Get;

/**
 * @OA\Get(
 *     path="/api/call-links/{code}",
 *     summary="Кому звонит гость",
 *     description="Публичный: по коду ссылки показывает её владельца, без email.",
 *     tags={"Ссылки для звонка"},
 *     security={},
 *     @OA\Parameter(name="code", in="path", required=true, @OA\Schema(type="string", example="aB3dE5fG7hJ9")),
 *     @OA\Response(
 *         response=200,
 *         description="Ссылка действует",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Ссылка для звонка."),
 *             @OA\Property(property="data", ref="#/components/schemas/CallLinkInviteResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=404, ref="#/components/responses/CallLink.CallLinkNotFoundException")
 * )
 */
final class ShowCallLinkController extends BaseController
{
    #[Get('call-links/{code}')]
    public function __invoke(string $code, ShowCallLinkAction $action): JsonResponse
    {
        return ApiResponder::ok(CallLinkInviteResource::make($action->run($code)), Translator::get('messages.call_link.show'));
    }
}
