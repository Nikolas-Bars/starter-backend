<?php

declare(strict_types=1);

namespace App\Modules\User\DTO;

use Spatie\LaravelData\Data;

final class ListUsersDTO extends Data
{
    /**
     * @param string|null $search Часть ника без «@»; null — без фильтра
     */
    public function __construct(
        public ?string $search = null,
        public int $per_page = 20,
    ) {
    }
}
