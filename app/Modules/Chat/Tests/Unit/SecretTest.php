<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Unit;

use App\Services\RedactSecretsLogProcessor;
use App\Services\Secret;
use App\Services\SecretReader;
use DateTimeImmutable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use LogicException;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

/**
 * Ключ нейросети не должен утечь ни в дамп, ни в JSON, ни в очередь, ни в лог.
 */
final class SecretTest extends TestCase
{
    private const KEY = 'sk-proj-AbCdEfGhIjKlMnOpQrStUvWxYz0123456789';

    public function testSecretIsMaskedEverywhereExceptReveal(): void
    {
        $secret = new Secret(self::KEY);

        self::assertSame(self::KEY, $secret->reveal());
        self::assertStringNotContainsString(self::KEY, \print_r($secret, true));
        self::assertStringNotContainsString(self::KEY, (string)\json_encode(['key' => $secret]));

        \ob_start();
        \var_dump($secret);
        self::assertStringNotContainsString(self::KEY, (string)\ob_get_clean());
    }

    public function testSecretCannotBeSerialized(): void
    {
        $this->expectException(LogicException::class);

        \serialize(new Secret(self::KEY));
    }

    public function testReaderPrefersFileAndTrimsIt(): void
    {
        $file = \tempnam(\sys_get_temp_dir(), 'key');
        self::assertIsString($file);
        \file_put_contents($file, self::KEY . "\n");

        Config::set('translation.providers.openai.api_key', 'sk-from-env-0000000000000000');
        Config::set('translation.providers.openai.api_key_file', $file);

        try {
            self::assertSame(self::KEY, (new SecretReader())->read('translation.providers.openai.api_key')?->reveal());
        } finally {
            \unlink($file);
        }
    }

    public function testReaderFallsBackToValueAndTreatsMissingAsOff(): void
    {
        Config::set('translation.providers.openai.api_key', '  ' . self::KEY . ' ');
        Config::set('translation.providers.openai.api_key_file', null);
        self::assertSame(self::KEY, (new SecretReader())->read('translation.providers.openai.api_key')?->reveal());

        Config::set('translation.providers.openai.api_key', '');
        self::assertNull((new SecretReader())->read('translation.providers.openai.api_key'));

        Config::set('translation.providers.openai.api_key', self::KEY);
        Config::set('translation.providers.openai.api_key_file', '/nonexistent/openai-api-key');
        self::assertNull((new SecretReader())->read('translation.providers.openai.api_key'));
    }

    public function testKeysAreNotConfiguredInTests(): void
    {
        foreach (['openai', 'anthropic'] as $provider) {
            self::assertNull((new SecretReader())->read("translation.providers.{$provider}.api_key"));
        }
    }

    public function testLogProcessorRedactsKeysAndTokens(): void
    {
        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Error,
            message: 'OpenAI 401: Incorrect API key provided: ' . self::KEY,
            context: ['headers' => ['Authorization' => 'Bearer ' . self::KEY, 'x-api-key' => 'sk-ant-api03-' . \str_repeat('a', 40)]],
        );

        $redacted = (new RedactSecretsLogProcessor())($record);
        $text     = $redacted->message . \json_encode($redacted->context);

        self::assertStringNotContainsString('AbCdEfGhIjKl', $text);
        self::assertStringNotContainsString(\str_repeat('a', 40), $text);
        self::assertStringContainsString('Bearer ' . Secret::MASK, $text);
        self::assertStringContainsString('Incorrect API key provided: ' . Secret::MASK, $text);
    }

    public function testLogChannelsRedactKeys(): void
    {
        $file = \tempnam(\sys_get_temp_dir(), 'log');
        self::assertIsString($file);

        Config::set('logging.channels.single.path', $file);

        try {
            Log::channel('single')->error('key ' . self::KEY);

            $contents = (string)\file_get_contents($file);
            self::assertStringContainsString('key ' . Secret::MASK, $contents);
            self::assertStringNotContainsString(self::KEY, $contents);
        } finally {
            \unlink($file);
        }
    }
}
