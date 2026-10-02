<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Auth\Http\Resources\AuthTokenResource;
use App\Modules\CallLink\Actions\JoinCallLinkAction;
use App\Modules\CallLink\Http\Requests\JoinCallLinkRequest;
use App\Services\ApiResponder;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Post;

/**
 * @OA\Post(
 *     path="/api/call-links/{code}/join",
 *     summary="Войти гостем по ссылке",
 *     description="Создаёт гостя владельца ссылки и выдаёт токен на несколько часов. Гость может позвонить только владельцу ссылки; остальное API ему закрыто. Не более 10 попыток в минуту с одного IP.",
 *     tags={"Ссылки для звонка"},
 *     security={},
 *     @OA\Parameter(name="code", in="path", required=true, @OA\Schema(type="string", example="aB3dE5fG7hJ9")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/JoinCallLinkRequest")),
 *     @OA\Response(
 *         response=201,
 *         description="Гость создан",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Можно звонить."),
 *             @OA\Property(property="data", ref="#/components/schemas/AuthTokenResource"),
 *             @OA\Property(property="errors", type="object", example={})
 *         )
 *     ),
 *     @OA\Response(response=404, ref="#/components/responses/CallLink.CallLinkNotFoundException"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
 *     @OA\Response(response=429, ref="#/components/responses/TooManyRequests")
 * )
 */
final class JoinCallLinkController extends BaseController
{
    #[Post('call-links/{code}/join', middleware: 'throttle:call-link-join')]
    public function __invoke(string $code, JoinCallLinkRequest $request, JoinCallLinkAction $action): JsonResponse
    {
        $token = $action->run($code, $request->toDTO());

        return ApiResponder::created(AuthTokenResource::make($token), Translator::get('messages.call_link.joined'));
    }
}
