<?php

declare(strict_types=1);

namespace App\Services\Translation;

use App\Services\Secret;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Messages API: структурированный ответ через обязательный вызов инструмента с той же схемой.
 * Ключ — только в заголовке x-api-key, никогда в URL и в тексте ошибок.
 */
final readonly class AnthropicTranslator implements MessageTranslator
{
    private const string PROVIDER = 'anthropic';

    private const string API_VERSION = '2023-06-01';

    private const string TOOL = 'submit_translation';

    public function __construct(
        private Secret $apiKey,
        private string $model,
        private string $baseUrl,
        private int $timeout,
    ) {
    }

    public function translate(TranslationRequest $request): TranslationResult
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->withHeaders(['x-api-key' => $this->apiKey->reveal(), 'anthropic-version' => self::API_VERSION])
                ->acceptJson()
                ->timeout($this->timeout)
                ->post('messages', [
                    'model'       => $this->model,
                    'max_tokens'  => 2048,
                    'temperature' => 0.2,
                    'system'      => TranslationPrompt::system(),
                    'messages'    => [
                        ['role' => 'user', 'content' => TranslationPrompt::user($request)],
                    ],
                    'tools' => [[
                        'name'         => self::TOOL,
                        'description'  => 'Return the detected language and the translations',
                        'input_schema' => TranslationPrompt::schema(),
                    ]],
                    'tool_choice' => ['type' => 'tool', 'name' => self::TOOL],
                ]);
        } catch (ConnectionException) {
            throw new TranslationFailedException(self::PROVIDER . ': нет соединения');
        }

        if (!$response->successful()) {
            throw new TranslationFailedException(self::PROVIDER . ': HTTP ' . $response->status());
        }

        $blocks = $response->json('content');

        foreach (\is_array($blocks) ? $blocks : [] as $block) {
            if (\is_array($block) && ($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === self::TOOL) {
                return TranslationPrompt::fromArray(self::PROVIDER, $block['input'] ?? null, $request);
            }
        }

        throw new TranslationFailedException(self::PROVIDER . ': пустой ответ');
    }
}
