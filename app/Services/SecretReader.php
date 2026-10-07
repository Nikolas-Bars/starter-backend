<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Ключи внешних сервисов (нейросеть для перевода и т.п.). В проде ключ лежит файлом в deploy/secrets/,
 * смонтированным только для чтения, и читается в момент использования: тогда его нет ни в окружении
 * контейнера (docker inspect, /proc/<pid>/environ), ни в кэше конфига. Локально ключ можно задать
 * прямо переменной окружения.
 */
final class SecretReader
{
    /**
     * @param string $configKey Ключ конфига со значением секрета; путь к файлу с ним — в "{$configKey}_file"
     */
    public function read(string $configKey): ?Secret
    {
        $file = Config::get("{$configKey}_file");

        if (\is_string($file) && $file !== '') {
            // Нет файла — сервис выключен, как push без ключа Firebase
            if (!\is_file($file)) {
                return null;
            }

            $value = \is_readable($file) ? \file_get_contents($file) : false;

            if ($value === false) {
                // Путь не секрет, а без этой строки ключ, который «не работает», придётся искать долго
                Log::warning('Не удалось прочитать файл с ключом', ['config' => "{$configKey}_file", 'path' => $file]);

                return null;
            }
        } else {
            $value = Config::get($configKey);
        }

        $value = \is_string($value) ? \trim($value) : '';

        return $value === '' ? null : new Secret($value);
    }
}
