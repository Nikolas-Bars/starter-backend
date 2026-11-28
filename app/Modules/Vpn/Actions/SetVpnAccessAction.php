<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Actions;

use App\Actions\BaseAction;
use App\Modules\User\Models\User;
use App\Modules\User\Tasks\FindUserByEmailTask;
use App\Modules\User\Tasks\FindUserByIdTask;
use App\Modules\Vpn\Tasks\GrantVpnAccessTask;
use App\Modules\Vpn\Tasks\RevokeVpnAccessTask;
use InvalidArgumentException;

final class SetVpnAccessAction extends BaseAction
{
    public function __construct(
        private readonly FindUserByIdTask    $findUserByIdTask,
        private readonly FindUserByEmailTask $findUserByEmailTask,
        private readonly GrantVpnAccessTask  $grantVpnAccessTask,
        private readonly RevokeVpnAccessTask $revokeVpnAccessTask,
    ) {
    }

    /**
     * @param string $user id или email пользователя
     *
     *
     * @throws InvalidArgumentException Пользователя нет или это гость по ссылке
     * @return array{user: User, changed: bool} changed — false, если доступ уже был таким
     */
    public function run(string $user, bool $allowed): array
    {
        $found = \ctype_digit($user)
            ? $this->findUserByIdTask->run((int)$user)
            : $this->findUserByEmailTask->run($user);

        if ($found === null) {
            throw new InvalidArgumentException("пользователь «{$user}» не найден (нужен id или email)");
        }

        if ($allowed) {
            if ($found->isGuest()) {
                throw new InvalidArgumentException('гостю по ссылке для звонка VPN выдать нельзя');
            }

            return ['user' => $found, 'changed' => $this->grantVpnAccessTask->run($found->id)];
        }

        return ['user' => $found, 'changed' => $this->revokeVpnAccessTask->run($found->id)];
    }
}
