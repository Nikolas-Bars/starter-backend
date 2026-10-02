<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\CallLink\Actions\RotateCallLinkAction;
use App\Modules\CallLink\Http\Resources\CallLinkResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Post;

/**
 * @OA\Post(
 *     path="/api/call-link/rotate",
 *     summary="Перевыпустить ссылку для звонка",
 *     description="Новый код ссылки; старая ссылка сразу перестаёт работать. Гостям недоступно.",
 *     tags={"Ссылки для звонка"},
 *     security={{"sanctum": {}}},
 *     @OA\Response(
 *         response=200,
 *         description="Новая ссылка",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Ссылка перевыпущена: старая больше не работает."),
 *             @OA\Property(property="data", ref="#/components/schemas/CallLinkResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/User.GuestNotAllowedException")
 * )
 */
final class RotateCallLinkController extends BaseController
{
    #[Post('call-link/rotate', middleware: ['auth:sanctum', 'not_guest'])]
    public function __invoke(Request $request, RotateCallLinkAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::ok(CallLinkResource::make($action->run($user)), Translator::get('messages.call_link.rotated'));
    }
}
