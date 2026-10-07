<?php

declare(strict_types=1);

namespace App\Services;

use JsonSerializable;
use LogicException;
use SensitiveParameter;

/**
 * Ключ внешнего сервиса. В var_dump/dump, логах и JSON вместо значения — маска, сериализовать
 * (а значит, положить в очередь или кэш) нельзя. Значение достаётся только явно, через reveal(),
 * в момент запроса к сервису.
 */
final class Secret implements JsonSerializable
{
    public const MASK = '********';

    public function __construct(
        #[SensitiveParameter]
        private readonly string $value,
    ) {
    }

    public function reveal(): string
    {
        return $this->value;
    }

    /**
     * @return array{value: string}
     */
    public function __debugInfo(): array
    {
        return ['value' => self::MASK];
    }

    public function jsonSerialize(): string
    {
        return self::MASK;
    }

    /**
     * @return array<never>
     */
    public function __serialize(): array
    {
        throw new LogicException('Секрет нельзя сериализовать: он попал бы в очередь, кэш или лог.');
    }

    /**
     * @param array<mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new LogicException('Секрет нельзя сериализовать: он попал бы в очередь, кэш или лог.');
    }
}
