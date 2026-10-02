<?php

declare(strict_types=1);

namespace App\Modules\Call\WebSockets;

/**
 * Список Origin, с которых можно открыть сокет (см. config/calls.php).
 * Origin мобильного приложения браузер подставить не может, поэтому он добавляется к сайтам без риска.
 */
final class AllowedOrigins
{
    /**
     * @param  list<string> $siteOrigins Пусто — проверка Origin отключена
     * @param  list<string> $appOrigins
     * @return list<string>
     */
    public static function merge(array $siteOrigins, array $appOrigins): array
    {
        if ($siteOrigins === []) {
            return [];
        }

        return \array_values(\array_unique([...$siteOrigins, ...$appOrigins]));
    }
}
