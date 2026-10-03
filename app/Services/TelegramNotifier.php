<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;

/**
 * Служебные сообщения администратору в Telegram (тот же бот и чат, куда приходят бэкапы).
 * Без токена или чата ничего не отправляет.
 */
final readonly class TelegramNotifier
{
    public function __construct(
        private HttpFactory $http,
        private ?string $botToken,
        private ?string $chatId,
    ) {
    }

    public function enabled(): bool
    {
        return $this->botToken !== null && $this->botToken !== '' && $this->chatId !== null && $this->chatId !== '';
    }

    public function send(string $text): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        try {
            $response = $this->http->createPendingRequest()->asForm()->timeout(10)->post(
                'https://api.telegram.org/bot' . $this->botToken . '/sendMessage',
                ['chat_id' => $this->chatId, 'text' => $text, 'disable_web_page_preview' => 'true'],
            );
        } catch (ConnectionException $exception) {
            Log::warning('Telegram недоступен', ['error' => $exception->getMessage()]);

            return false;
        }

        if ($response->failed()) {
            // В тексте ошибки нет токена: он только в адресе запроса
            Log::warning('Telegram не принял сообщение', ['status' => $response->status(), 'body' => $response->body()]);

            return false;
        }

        return true;
    }
}
