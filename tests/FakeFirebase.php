<?php

declare(strict_types=1);

namespace Tests;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Ключ сервисного аккаунта с настоящей RSA-подписью и подменённые ответы Google:
 * push «включены», но в сеть ничего не уходит.
 */
final class FakeFirebase
{
    public const PROJECT = 'starter-test';

    public const ACCESS_TOKEN = 'ya29.test-access-token';

    /**
     * @param array<string, int> $statusByToken Ответ FCM для конкретного токена устройства (по умолчанию 200)
     */
    public static function enable(array $statusByToken = []): void
    {
        $key = \openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        if ($key === false || !\openssl_pkey_export($key, $privateKey)) {
            throw new RuntimeException('Не удалось создать тестовый ключ RSA');
        }

        $path = \tempnam(\sys_get_temp_dir(), 'fcm');

        if ($path === false) {
            throw new RuntimeException('Не удалось создать файл ключа');
        }

        \file_put_contents($path, \json_encode([
            'type'         => 'service_account',
            'project_id'   => self::PROJECT,
            'client_email' => 'push@' . self::PROJECT . '.iam.gserviceaccount.com',
            'private_key'  => $privateKey,
            'token_uri'    => 'https://oauth2.googleapis.com/token',
        ], JSON_THROW_ON_ERROR));

        Config::set('push.fcm.credentials', $path);

        Http::fake(static function (Request $request) use ($statusByToken): PromiseInterface {
            if (\str_starts_with($request->url(), 'https://oauth2.googleapis.com/')) {
                return Http::response(['access_token' => self::ACCESS_TOKEN, 'expires_in' => 3599]);
            }

            $token  = $request['message']['token'] ?? null;
            $status = \is_string($token) ? ($statusByToken[$token] ?? 200) : 400;

            return match ($status) {
                200 => Http::response(['name' => 'projects/' . self::PROJECT . '/messages/1']),
                400 => Http::response(['error' => ['code' => 400, 'details' => [
                    ['errorCode' => 'INVALID_ARGUMENT'],
                    ['fieldViolations' => [['field' => 'message.token', 'description' => 'not a valid FCM registration token']]],
                ]]], 400),
                404     => Http::response(['error' => ['code' => 404, 'details' => [['errorCode' => 'UNREGISTERED']]]], 404),
                default => Http::response(['error' => ['code' => $status, 'details' => [['errorCode' => 'INTERNAL']]]], $status),
            };
        });
    }
}
