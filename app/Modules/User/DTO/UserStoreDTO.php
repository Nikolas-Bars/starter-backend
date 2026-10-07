<?php

declare(strict_types=1);

namespace App\Modules\User\DTO;

use Spatie\LaravelData\Data;

final class UserStoreDTO extends Data
{
    /**
     * @param string $password Пароль в открытом виде — хэшируется кастом модели при сохранении
     * @param string $locale   Язык интерфейса из app.supported_locales
     */
    public function __construct(
        public string $name,
        public string $username,
        public string $email,
        public string $password,
        public string $locale,
    ) {
    }
}
