<?php

declare(strict_types=1);

namespace App\Services\Translation;

use RuntimeException;

/**
 * Провайдер не ответил или ответил не по формату. В тексте — только провайдер и код ответа:
 * ни ключа, ни текста сообщений (исключение попадает в лог).
 */
final class TranslationFailedException extends RuntimeException
{
}
