<?php

declare(strict_types=1);

namespace App\Services\Translation;

final readonly class TranslationResult
{
    /**
     * @param string                $sourceLocale Язык оригинала (ISO 639-1), как его определила нейросеть
     * @param array<string, string> $translations locale => текст; язык оригинала сюда не входит
     */
    public function __construct(
        public string $sourceLocale,
        public array $translations,
    ) {
    }
}
