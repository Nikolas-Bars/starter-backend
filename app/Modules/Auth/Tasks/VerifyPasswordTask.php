<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tasks;

use App\Modules\User\Models\User;
use App\Tasks\BaseTask;
use Illuminate\Support\Facades\Hash;

final class VerifyPasswordTask extends BaseTask
{
    /**
     * Хэш несуществующего пароля: для отсутствующего пользователя всё равно
     * выполняем bcrypt-сравнение, чтобы по времени ответа нельзя было понять,
     * зарегистрирован ли email.
     */
    private const DUMMY_HASH = '$2y$12$7JyK5iE5Va.kVjMlryweT.85mqHvKJkWD5Us/iSt0x9fJ1wSobeRC';

    public function run(?User $user, string $password): bool
    {
        if ($user === null) {
            Hash::check($password, self::DUMMY_HASH);

            return false;
        }

        return Hash::check($password, $user->password);
    }
}
