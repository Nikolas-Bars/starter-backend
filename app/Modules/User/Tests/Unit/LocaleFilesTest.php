<?php

declare(strict_types=1);

namespace App\Modules\User\Tests\Unit;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Каждый язык из app.supported_locales переводит всё, что есть в русском: иначе клиент
 * молча получит русский текст из fallback_locale.
 */
final class LocaleFilesTest extends TestCase
{
    private const BASE_LOCALE = 'ru';

    public function testEverySupportedLocaleHasAllBaseKeys(): void
    {
        $files = \glob(lang_path(self::BASE_LOCALE . '/*.php'));
        self::assertNotEmpty($files);

        foreach (Config::array('app.supported_locales') as $locale) {
            self::assertIsString($locale);

            foreach ($files as $file) {
                $group = \basename($file, '.php');
                $path  = lang_path("{$locale}/{$group}.php");

                self::assertFileExists($path, "Нет файла lang/{$locale}/{$group}.php");

                $missing = \array_diff($this->keys($file), $this->keys($path));

                self::assertSame([], \array_values($missing), "lang/{$locale}/{$group}.php: нет ключей");
            }
        }
    }

    /**
     * @return list<string>
     */
    private function keys(string $file): array
    {
        $lines = require $file;
        self::assertIsArray($lines);

        return \array_map(\strval(...), \array_keys(Arr::dot($lines)));
    }
}
