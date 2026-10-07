<?php

declare(strict_types=1);

namespace App\Modules\User\Models;

use App\Modules\User\Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property      int         $id
 * @property      int|null    $guest_of_id       Гость по ссылке для звонка: кто его пригласил
 * @property      string      $name              Имя пользователя
 * @property      string|null $username          Ник для поиска (нижний регистр, без @)
 * @property      string|null $avatar_path       Аватарка на диске вложений (JPEG 512×512)
 * @property      string      $locale            Язык интерфейса из app.supported_locales; на него переводятся входящие сообщения
 * @property      string      $email             Email, он же логин
 * @property      Carbon|null $email_verified_at Дата подтверждения email
 * @property      string      $password          Хэш пароля (bcrypt)
 * @property-read Carbon|null $created_at
 * @property-read Carbon|null $updated_at
 */
final class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'guest_of_id',
        'name',
        'username',
        'avatar_path',
        'locale',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    public function isGuest(): bool
    {
        return $this->guest_of_id !== null;
    }

    /**
     * Можно ли позвонить другому пользователю и видеть, в сети ли он:
     * гость по ссылке и владелец ссылки связаны только друг с другом.
     */
    public function canContact(self $other): bool
    {
        if ($this->isGuest()) {
            return $this->guest_of_id === $other->id;
        }

        return !$other->isGuest() || $other->guest_of_id === $this->id;
    }

    /**
     * Пароль хэшируется автоматически при записи в атрибут (bcrypt, BCRYPT_ROUNDS).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }
}
