<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\CallLink\Actions\GetOwnCallLinkAction;
use App\Modules\CallLink\Http\Resources\CallLinkResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;

/**
 * @OA\Get(
 *     path="/api/call-link",
 *     summary="Моя ссылка для звонка",
 *     description="Личная постоянная ссылка: по ней гость без регистрации звонит текущему пользователю. Создаётся при первом запросе. Гостям недоступно.",
 *     tags={"Ссылки для звонка"},
 *     security={{"sanctum": {}}},
 *     @OA\Response(
 *         response=200,
 *         description="Ссылка",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Ваша ссылка для звонка."),
 *             @OA\Property(property="data", ref="#/components/schemas/CallLinkResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/User.GuestNotAllowedException")
 * )
 */
final class GetOwnCallLinkController extends BaseController
{
    #[Get('call-link', middleware: ['auth:sanctum', 'not_guest'])]
    public function __invoke(Request $request, GetOwnCallLinkAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::ok(CallLinkResource::make($action->run($user)), Translator::get('messages.call_link.own'));
    }
}
