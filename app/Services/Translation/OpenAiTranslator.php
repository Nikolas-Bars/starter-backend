<?php

declare(strict_types=1);

namespace App\Services\Translation;

use App\Services\Secret;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Chat Completions со строгим JSON Schema. store=false: OpenAI не сохраняет переписку у себя
 * для дашборда и дообучения. Ключ — только в заголовке, никогда в URL и в тексте ошибок.
 */
final readonly class OpenAiTranslator implements MessageTranslator
{
    private const string PROVIDER = 'openai';

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
                ->withToken($this->apiKey->reveal())
                ->acceptJson()
                ->timeout($this->timeout)
                ->post('chat/completions', [
                    'model'       => $this->model,
                    'store'       => false,
                    'temperature' => 0.2,
                    'messages'    => [
                        ['role' => 'system', 'content' => TranslationPrompt::system()],
                        ['role' => 'user', 'content' => TranslationPrompt::user($request)],
                    ],
                    'response_format' => [
                        'type'        => 'json_schema',
                        'json_schema' => ['name' => 'translation', 'strict' => true, 'schema' => TranslationPrompt::schema()],
                    ],
                ]);
        } catch (ConnectionException) {
            throw new TranslationFailedException(self::PROVIDER . ': нет соединения');
        }

        if (!$response->successful()) {
            throw new TranslationFailedException(self::PROVIDER . ': HTTP ' . $response->status());
        }

        $content = $response->json('choices.0.message.content');

        if (!\is_string($content)) {
            throw new TranslationFailedException(self::PROVIDER . ': пустой ответ');
        }

        return TranslationPrompt::parse(self::PROVIDER, $content, $request);
    }
}
