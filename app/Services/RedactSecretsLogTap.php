<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Log\Logger;
use Monolog\Logger as Monolog;

/**
 * Вешает RedactSecretsLogProcessor на канал лога (config/logging.php, ключ tap).
 */
final class RedactSecretsLogTap
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if ($monolog instanceof Monolog) {
            $monolog->pushProcessor(new RedactSecretsLogProcessor());
        }
    }
}
