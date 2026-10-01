<?php

declare(strict_types=1);

namespace App\Modules\Auth\Actions;

use App\Actions\BaseAction;
use App\Modules\Auth\DTO\AuthTokenDTO;
use App\Modules\Auth\DTO\LoginDTO;
use App\Modules\Auth\Exceptions\InvalidCredentialsException;
use App\Modules\Auth\Tasks\IssueAccessTokenTask;
use App\Modules\Auth\Tasks\VerifyPasswordTask;
use App\Modules\User\Tasks\FindUserByEmailTask;

final class LoginAction extends BaseAction
{
    public function __construct(
        private readonly FindUserByEmailTask  $findUserByEmailTask,
        private readonly VerifyPasswordTask   $verifyPasswordTask,
        private readonly IssueAccessTokenTask $issueAccessTokenTask,
    ) {
    }

    /**
     * @throws InvalidCredentialsException
     */
    public function run(LoginDTO $dto): AuthTokenDTO
    {
        $user = $this->findUserByEmailTask->run($dto->email);

        // Одна ошибка на «нет пользователя» и «неверный пароль» — email не перебрать
        if (!$this->verifyPasswordTask->run($user, $dto->password) || $user === null) {
            throw new InvalidCredentialsException();
        }

        return $this->issueAccessTokenTask->run($user, $dto->device_name);
    }
}
