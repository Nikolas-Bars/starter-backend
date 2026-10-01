<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Типобезопасная обёртка над __(): всегда возвращает строку.
 * Если ключа нет ни в одной локали, вернётся сам ключ — так пропущенный перевод сразу виден.
 */
final class Translator
{
    /**
     * @param array<string, int|float|string> $replace
     */
    public static function get(string $key, array $replace = []): string
    {
        $message = __($key, $replace);

        return \is_string($message) ? $message : $key;
    }

    /**
     * Группа переводов, например названия полей формы из lang/{locale}/fields.php.
     *
     * @return array<string, string>
     */
    public static function group(string $key): array
    {
        $group = __($key);

        if (!\is_array($group)) {
            return [];
        }

        /** @var array<string, string> */
        return \array_filter($group, \is_string(...));
    }
}
