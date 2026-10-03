<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Date;

/**
 * Подписанные ссылки на вложения: /api/files/{path}?expires=…&signature=…
 *
 * Срок — начало текущих суток по UTC плюс двое суток: в течение дня ссылка на файл одна и та же,
 * поэтому браузер и приложение берут его из кэша, а не качают заново.
 */
final readonly class FileUrlSigner
{
    public const string PREFIX = '/api/files/';

    private const int LIFETIME_DAYS = 2;

    public function __construct(private string $key)
    {
    }

    public function url(string $path): string
    {
        $expires = Date::now('UTC')->startOfDay()->addDays(self::LIFETIME_DAYS)->getTimestamp();

        return self::PREFIX . \implode('/', \array_map(\rawurlencode(...), \explode('/', $path)))
            . '?expires=' . $expires . '&signature=' . $this->signature($path, $expires);
    }

    public function verify(string $path, string $expires, string $signature): bool
    {
        if ($path === '' || \preg_match('/^\d{1,12}$/', $expires) !== 1) {
            return false;
        }

        $expiresAt = (int)$expires;

        return $expiresAt > Date::now()->getTimestamp()
            && \hash_equals($this->signature($path, $expiresAt), $signature);
    }

    private function signature(string $path, int $expires): string
    {
        return \hash_hmac('sha256', $path . '|' . $expires, $this->key);
    }
}
