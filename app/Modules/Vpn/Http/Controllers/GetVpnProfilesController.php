<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\User\Models\User;
use App\Modules\Vpn\Actions\GetVpnProfilesAction;
use App\Modules\Vpn\Http\Resources\VpnProfileResource;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;

/**
 * @OA\Get(
 *     path="/api/vpn/profiles",
 *     summary="Профили VPN",
 *     description="Включённые профили VPN по порядку (position): приложение показывает их списком и перебирает по очереди. Только пользователям, которым выдан доступ (artisan vpn:access); остальным 403 — раздел VPN не показывается.",
 *     tags={"VPN"},
 *     security={{"sanctum": {}}},
 *     @OA\Response(
 *         response=200,
 *         description="Профили",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Профили VPN."),
 *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/VpnProfileResource")),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/Vpn.VpnNotAllowedException")
 * )
 */
final class GetVpnProfilesController extends BaseController
{
    #[Get('vpn/profiles', middleware: ['auth:sanctum', 'not_guest'])]
    public function __invoke(Request $request, GetVpnProfilesAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::ok(VpnProfileResource::collection($action->run($user)), Translator::get('messages.vpn.profiles'));
    }
}
