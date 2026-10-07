<?php

declare(strict_types=1);

namespace App\Services\Translation;

/**
 * Переводчик сообщений чата. Реализация на провайдера (OpenAiTranslator, AnthropicTranslator) знает
 * только его формат запроса и ответа; текст промпта и разбор результата — в TranslationPrompt.
 * Какой выбран — TranslatorFactory по translation.provider.
 */
interface MessageTranslator
{
    /**
     * @throws TranslationFailedException
     */
    public function translate(TranslationRequest $request): TranslationResult;
}
