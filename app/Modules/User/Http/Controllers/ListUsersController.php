<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\User\Actions\ListUsersAction;
use App\Modules\User\Http\Requests\ListUsersRequest;
use App\Modules\User\Http\Resources\UserResource;
use App\Modules\User\Models\User;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Get;

/**
 * @OA\Get(
 *     path="/api/users",
 *     summary="Список пользователей",
 *     description="Все пользователи, кроме текущего, по алфавиту. По 20 на страницу.",
 *     tags={"Пользователи"},
 *     security={{"sanctum": {}}},
 *     @OA\Parameter(ref="#/components/parameters/ListUsersRequest.search"),
 *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", minimum=1, example=1)),
 *     @OA\Response(
 *         response=200,
 *         description="Страница списка",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Список пользователей."),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/UserResource")),
 *                 @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
 *             ),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError")
 * )
 */
final class ListUsersController extends BaseController
{
    #[Get('users', middleware: 'auth:sanctum')]
    public function __invoke(ListUsersRequest $request, ListUsersAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponder::paginated(
            $action->run($user, $request->toDTO()),
            UserResource::class,
            Translator::get('messages.user.list'),
        );
    }
}
