<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * Отправка push через Firebase Cloud Messaging (HTTP v1). Авторизация — ключом сервисного аккаунта:
 * подписанный JWT меняем на OAuth-токен Google и держим его в кеше, пока не истечёт.
 *
 * Шлём только data-сообщения: как показать звонок, решает приложение, а не система.
 */
final class FcmClient
{
    public const SENT = 'sent';

    /**
     * Приложение удалено или токен перевыпущен: устройство надо забыть
     */
    public const INVALID_TOKEN = 'invalid_token';

    public const FAILED = 'failed';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const CACHE_KEY = 'push:fcm:access-token';

    private const STALE_TOKEN_ERRORS = ['UNREGISTERED' => true, 'SENDER_ID_MISMATCH' => true];

    /**
     * @var array{project_id: string, client_email: string, private_key: string, token_uri: string}|false|null
     */
    private array|false|null $credentials = null;

    public function enabled(): bool
    {
        return $this->credentials() !== null;
    }

    /**
     * @param array<string, string> $data Значения FCM принимает только строками
     *
     * @return self::SENT|self::INVALID_TOKEN|self::FAILED
     */
    public function send(string $token, array $data, int $ttlSeconds): string
    {
        $credentials = $this->credentials();

        if ($credentials === null) {
            return self::FAILED;
        }

        $message = [
            'message' => [
                'token'   => $token,
                'data'    => $data,
                'android' => ['priority' => 'HIGH', 'ttl' => \max(0, $ttlSeconds) . 's'],
            ],
        ];

        try {
            $accessToken = $this->accessToken($credentials);

            if ($accessToken === null) {
                return self::FAILED;
            }

            $response = Http::timeout(Config::integer('push.fcm.timeout'))
                ->withToken($accessToken)
                ->post('https://fcm.googleapis.com/v1/projects/' . $credentials['project_id'] . '/messages:send', $message);
        } catch (ConnectionException $exception) {
            Log::warning('FCM недоступен', ['error' => $exception->getMessage()]);

            return self::FAILED;
        }

        if ($response->successful()) {
            return self::SENT;
        }

        if ($response->status() === 401) {
            Cache::forget(self::CACHE_KEY);
        }

        if ($this->isInvalidToken($response)) {
            return self::INVALID_TOKEN;
        }

        Log::warning('FCM не принял сообщение', ['status' => $response->status(), 'body' => $response->body()]);

        return self::FAILED;
    }

    /**
     * UNREGISTERED — приложение удалено, SENDER_ID_MISMATCH — токен от другого проекта Firebase.
     * Прочие ошибки (в том числе 400 из-за содержимого) устройство не удаляют
     */
    private function isInvalidToken(Response $response): bool
    {
        $details = $response->json('error.details');

        foreach (\is_array($details) ? $details : [] as $detail) {
            if (\is_array($detail) && \is_string($detail['errorCode'] ?? null) && isset(self::STALE_TOKEN_ERRORS[$detail['errorCode']])) {
                return true;
            }
        }

        return $response->status() === 404;
    }

    /**
     * @param array{project_id: string, client_email: string, private_key: string, token_uri: string} $credentials
     *
     * @throws ConnectionException
     */
    private function accessToken(array $credentials): ?string
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (\is_string($cached) && $cached !== '') {
            return $cached;
        }

        $assertion = $this->signedAssertion($credentials);

        if ($assertion === null) {
            return null;
        }

        $response = Http::asForm()
            ->timeout(Config::integer('push.fcm.timeout'))
            ->post($credentials['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $assertion,
            ]);

        $token     = $response->json('access_token');
        $expiresIn = $response->json('expires_in');

        if (!$response->successful() || !\is_string($token) || $token === '') {
            Log::warning('Google не выдал токен для FCM', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        // Токен живёт час; обновляем заранее, чтобы не отправить запрос с только что истёкшим
        Cache::put(self::CACHE_KEY, $token, \max(60, (\is_int($expiresIn) ? $expiresIn : 3600) - 300));

        return $token;
    }

    /**
     * @param array{project_id: string, client_email: string, private_key: string, token_uri: string} $credentials
     */
    private function signedAssertion(array $credentials): ?string
    {
        $now = \time();

        try {
            $header = $this->base64Url(\json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $claims = $this->base64Url(\json_encode([
                'iss'   => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud'   => $credentials['token_uri'],
                'iat'   => $now,
                'exp'   => $now + 3600,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } catch (JsonException) {
            return null;
        }

        $signature = '';

        if (!\openssl_sign($header . '.' . $claims, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            Log::warning('Не удалось подписать запрос к Google: проверьте private_key в ключе Firebase');

            return null;
        }

        /** @var string $signature */
        return $header . '.' . $claims . '.' . $this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return \rtrim(\strtr(\base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @return array{project_id: string, client_email: string, private_key: string, token_uri: string}|null
     */
    private function credentials(): ?array
    {
        if ($this->credentials === null) {
            $this->credentials = $this->loadCredentials() ?? false;
        }

        return $this->credentials === false ? null : $this->credentials;
    }

    /**
     * @return array{project_id: string, client_email: string, private_key: string, token_uri: string}|null
     */
    private function loadCredentials(): ?array
    {
        $path = Config::string('push.fcm.credentials');

        if ($path === '' || !\is_file($path)) {
            return null;
        }

        $json = \json_decode((string)\file_get_contents($path), true);

        if (!\is_array($json)) {
            Log::warning('Ключ Firebase не похож на JSON', ['path' => $path]);

            return null;
        }

        $fields = [];

        foreach (['project_id', 'client_email', 'private_key'] as $field) {
            if (!\is_string($json[$field] ?? null) || $json[$field] === '') {
                Log::warning('В ключе Firebase нет поля ' . $field, ['path' => $path]);

                return null;
            }

            $fields[$field] = $json[$field];
        }

        $tokenUri = $json['token_uri'] ?? null;

        return [
            'project_id'   => $fields['project_id'],
            'client_email' => $fields['client_email'],
            'private_key'  => $fields['private_key'],
            'token_uri'    => \is_string($tokenUri) && $tokenUri !== '' ? $tokenUri : 'https://oauth2.googleapis.com/token',
        ];
    }
}
