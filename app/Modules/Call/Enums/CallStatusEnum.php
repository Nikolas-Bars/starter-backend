<?php

declare(strict_types=1);

namespace App\Modules\Call\Enums;

/**
 * Жизненный цикл звонка: ringing → active → ended. Остальные статусы — финальные исходы
 * звонка, который так и не начался.
 */
enum CallStatusEnum: string
{
    /**
     * Ждём ответа собеседника
     */
    case Ringing = 'ringing';

    /**
     * Разговор идёт
     */
    case Active = 'active';

    /**
     * Собеседник отклонил вызов
     */
    case Rejected = 'rejected';

    /**
     * Никто не ответил или звонящий сбросил до ответа
     */
    case Missed = 'missed';

    /**
     * Собеседник уже разговаривает
     */
    case Busy = 'busy';

    /**
     * Собеседник не в сети
     */
    case Unavailable = 'unavailable';

    /**
     * Разговор завершён
     */
    case Ended = 'ended';

    public function isOngoing(): bool
    {
        return $this === self::Ringing || $this === self::Active;
    }
}
