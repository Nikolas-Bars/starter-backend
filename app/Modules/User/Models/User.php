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
 * @property      string      $name              Имя пользователя
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
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

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
