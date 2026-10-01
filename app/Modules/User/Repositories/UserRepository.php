<?php

declare(strict_types=1);

namespace App\Modules\User\Repositories;

use App\Modules\User\DTO\UserStoreDTO;
use App\Modules\User\Models\User;
use App\Repositories\BaseRepository;

/**
 * @extends BaseRepository<User>
 */
final class UserRepository extends BaseRepository
{
    /**
     * @return class-string<User>
     */
    public function model(): string
    {
        return User::class;
    }

    public function findByEmail(string $email): ?User
    {
        return $this->query()->where('email', $email)->first();
    }

    public function store(UserStoreDTO $dto): User
    {
        // Пароль хэшируется cast'ом `hashed` модели — сюда приходит открытым текстом
        return $this->create([
            'name'     => $dto->name,
            'email'    => $dto->email,
            'password' => $dto->password,
        ]);
    }
}
