<?php

declare(strict_types=1);

namespace App\Services\Translation;

use App\Services\SecretReader;
use Illuminate\Support\Facades\Config;

/**
 * Переводчик выбранного в translation.provider провайдера. Сменить провайдера — одна переменная
 * окружения TRANSLATION_PROVIDER и ключ для него; код и промпт те же.
 */
final readonly class TranslatorFactory
{
    public function __construct(
        private SecretReader $secrets,
    ) {
    }

    /**
     * null — у выбранного провайдера нет ключа: перевод выключен
     */
    public function make(): ?MessageTranslator
    {
        $provider = Config::string('translation.provider');
        $prefix   = "translation.providers.{$provider}";
        $apiKey   = $this->secrets->read("{$prefix}.api_key");

        if ($apiKey === null) {
            return null;
        }

        $model   = Config::string("{$prefix}.model");
        $baseUrl = Config::string("{$prefix}.base_url");
        $timeout = Config::integer('translation.timeout');

        return match ($provider) {
            'openai'    => new OpenAiTranslator($apiKey, $model, $baseUrl, $timeout),
            'anthropic' => new AnthropicTranslator($apiKey, $model, $baseUrl, $timeout),
            default     => null,
        };
    }

    public function enabled(): bool
    {
        return $this->make() !== null;
    }
}
