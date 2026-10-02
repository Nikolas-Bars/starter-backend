<?php

declare(strict_types=1);

namespace App\Modules\Call\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Call\Actions\ListCallHistoryAction;
use App\Modules\Call\Http\Resources\CallResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;

/**
 * @OA\Get(
 *     path="/api/calls",
 *     summary="История звонков",
 *     description="Входящие и исходящие звонки текущего пользователя, новые сверху. По 20 на страницу.",
 *     tags={"Звонки"},
 *     security={{"sanctum": {}}},
 *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", minimum=1, example=1)),
 *     @OA\Response(
 *         response=200,
 *         description="Страница истории",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="История звонков."),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/CallResource")),
 *                 @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
 *             ),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized")
 * )
 */
final class ListCallHistoryController extends BaseController
{
    private const PER_PAGE = 20;

    #[Get('calls', middleware: 'auth:sanctum')]
    public function __invoke(Request $request, ListCallHistoryAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::paginated(
            $action->run($user, self::PER_PAGE),
            CallResource::class,
            Translator::get('messages.call.history'),
        );
    }
}
