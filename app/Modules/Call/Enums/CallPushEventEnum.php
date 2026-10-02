<?php

declare(strict_types=1);

namespace App\Modules\Call\Enums;

/**
 * О чём сообщаем телефонам собеседника через push
 */
enum CallPushEventEnum: string
{
    /**
     * Звонят: показать входящий вызов
     */
    case Incoming = 'incoming';

    /**
     * Вызов больше не звонит (ответили, сбросили, пропущен): убрать уведомление
     */
    case Ended = 'ended';
}
