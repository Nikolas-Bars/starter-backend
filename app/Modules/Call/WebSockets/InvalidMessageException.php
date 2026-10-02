<?php

declare(strict_types=1);

namespace App\Modules\Call\WebSockets;

use RuntimeException;

/**
 * Сообщение клиента не соответствует протоколу сигнализации (неизвестный тип, нет обязательного поля).
 */
final class InvalidMessageException extends RuntimeException
{
}
