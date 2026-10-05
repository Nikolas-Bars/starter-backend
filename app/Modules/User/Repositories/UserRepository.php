<?php

declare(strict_types=1);

namespace App\Modules\User\Repositories;

use App\Modules\User\DTO\UserStoreDTO;
use App\Modules\User\Models\User;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

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

    public function findByAvatarPath(string $path): ?User
    {
        return $this->query()->where('avatar_path', $path)->first();
    }

    /**
     * Все пользователи, кроме указанного, по имени; поиск — только по вхождению в ник.
     * По имени и email не ищем: найти человека можно, только зная его ник.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateExcept(int $exceptId, ?string $search, int $perPage): LengthAwarePaginator
    {
        $query = $this->query()->whereKeyNot($exceptId);
        $query->getQuery()->whereNull('guest_of_id');

        if ($search !== null) {
            // Подстрока, а не LIKE: «_» разрешён в нике и должен искаться как есть (ники хранятся в нижнем регистре)
            $query->getQuery()->whereNotNull('username')->whereRaw('INSTR(username, ?) > 0', [\mb_strtolower($search)]);
        }

        $query->getQuery()->orderBy('name')->orderBy('id');

        return $query->paginate($perPage);
    }

    public function store(UserStoreDTO $dto): User
    {
        // Пароль хэшируется cast'ом `hashed` модели — сюда приходит открытым текстом
        return $this->create([
            'name'     => $dto->name,
            'username' => $dto->username,
            'email'    => $dto->email,
            'password' => $dto->password,
        ]);
    }

    public function updateProfile(User $user, string $name, ?string $username): User
    {
        $user->update([
            'name'     => $name,
            'username' => $username,
        ]);

        return $user;
    }

    public function updateAvatar(User $user, ?string $path): void
    {
        $user->update(['avatar_path' => $path]);
    }

    /**
     * Гость входит только по выданному токену: email и пароль случайные, войти с ними нельзя.
     */
    public function storeGuest(string $name, int $hostId): User
    {
        return $this->create([
            'guest_of_id' => $hostId,
            'name'        => $name,
            'email'       => 'guest-' . Str::uuid()->toString() . '@guest.invalid',
            'password'    => Str::random(64),
        ]);
    }
}
