<?php

declare(strict_types=1);

namespace App\Modules\Chat\Enums;

enum ChatTypeEnum: string
{
    /**
     * Личная переписка двух пользователей
     */
    case Direct = 'direct';
}
