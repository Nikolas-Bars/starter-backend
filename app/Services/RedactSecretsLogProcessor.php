<?php

declare(strict_types=1);

namespace App\Services;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Последний рубеж: если ключ API всё-таки попал в текст лога или его контекст (например, в ответе
 * сервиса об ошибке), он заменяется маской. Подключается ко всем каналам через RedactSecretsLogTap.
 */
final class RedactSecretsLogProcessor implements ProcessorInterface
{
    /**
     * Ключи OpenAI (sk-…, sk-proj-…) и Anthropic (sk-ant-…), Bearer-токены в заголовках.
     */
    private const PATTERNS = [
        '/\bsk-[A-Za-z0-9_\-]{16,}/',
        '/(Bearer\s+)[A-Za-z0-9._~+\/\-]{16,}=*/i',
        '/(x-api-key["\']?\s*[:=]\s*["\']?)[A-Za-z0-9_\-]{16,}/i',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: self::redact($record->message),
            context: self::redactArray($record->context),
            extra: self::redactArray($record->extra),
        );
    }

    public static function redact(string $text): string
    {
        return \preg_replace_callback(
            self::PATTERNS,
            static fn(array $match): string => ($match[1] ?? '') . Secret::MASK,
            $text,
        ) ?? $text;
    }

    /**
     * @param  array<mixed> $values
     * @return array<mixed>
     */
    private static function redactArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (\is_string($value)) {
                $values[$key] = self::redact($value);
            } elseif (\is_array($value)) {
                $values[$key] = self::redactArray($value);
            }
        }

        return $values;
    }
}
