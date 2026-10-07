<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Actions;

use App\Actions\BaseAction;
use App\Modules\Auth\DTO\AuthTokenDTO;
use App\Modules\Auth\Tasks\IssueAccessTokenTask;
use App\Modules\CallLink\DTO\JoinCallLinkDTO;
use App\Modules\CallLink\Exceptions\CallLinkNotFoundException;
use App\Modules\CallLink\Tasks\FindCallLinkByCodeTask;
use App\Modules\User\Tasks\CreateGuestUserTask;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

final class JoinCallLinkAction extends BaseAction
{
    public function __construct(
        private readonly FindCallLinkByCodeTask $findCallLinkByCodeTask,
        private readonly CreateGuestUserTask    $createGuestUserTask,
        private readonly IssueAccessTokenTask   $issueAccessTokenTask,
    ) {
    }

    /**
     * Заводит гостя владельца ссылки и выдаёт ему короткоживущий токен. Гостей не удаляем:
     * их звонки остаются в истории владельца, а войти снова без токена гость не может.
     *
     * @throws CallLinkNotFoundException
     */
    public function run(string $code, JoinCallLinkDTO $dto): AuthTokenDTO
    {
        $link = $this->findCallLinkByCodeTask->run($code) ?? throw new CallLinkNotFoundException();

        return DB::transaction(function () use ($link, $dto): AuthTokenDTO {
            $guest = $this->createGuestUserTask->run($dto->name, $link->user_id, App::getLocale());

            return $this->issueAccessTokenTask->run(
                $guest,
                $dto->device_name,
                Config::integer('calls.links.guest_token_ttl'),
            );
        });
    }
}
