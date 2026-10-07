<?php

declare(strict_types=1);

namespace App\Modules\Auth\Actions;

use App\Actions\BaseAction;
use App\Modules\Auth\DTO\AuthTokenDTO;
use App\Modules\Auth\DTO\RegisterDTO;
use App\Modules\Auth\Tasks\IssueAccessTokenTask;
use App\Modules\User\DTO\UserStoreDTO;
use App\Modules\User\Tasks\CreateUserTask;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

final class RegisterAction extends BaseAction
{
    public function __construct(
        private readonly CreateUserTask       $createUserTask,
        private readonly IssueAccessTokenTask $issueAccessTokenTask,
    ) {
    }

    /**
     * Создаёт пользователя и сразу выдаёт токен — после регистрации клиент уже авторизован.
     * Язык интерфейса — тот, на котором пришёл запрос (SetLocale).
     */
    public function run(RegisterDTO $dto, string $deviceName): AuthTokenDTO
    {
        return DB::transaction(function () use ($dto, $deviceName): AuthTokenDTO {
            $user = $this->createUserTask->run(new UserStoreDTO(
                name: $dto->name,
                username: $dto->username,
                email: $dto->email,
                password: $dto->password,
                locale: App::getLocale(),
            ));

            return $this->issueAccessTokenTask->run($user, $deviceName);
        });
    }
}
