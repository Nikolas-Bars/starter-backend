<?php

declare(strict_types=1);

namespace App\Modules\Vpn\DTO;

use Spatie\LaravelData\Data;

/**
 * Правка профиля: null — поле не менять.
 */
final class VpnProfileChangesDTO extends Data
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $uri = null,
        public readonly ?int $position = null,
        public readonly ?bool $isEnabled = null,
    ) {
    }

    /**
     * @return array<string, string|int|bool>
     */
    public function attributes(): array
    {
        return \array_filter([
            'name'       => $this->name,
            'uri'        => $this->uri,
            'position'   => $this->position,
            'is_enabled' => $this->isEnabled,
        ], static fn(string|int|bool|null $value): bool => $value !== null);
    }
}
