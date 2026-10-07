<?php

declare(strict_types=1);

namespace App\Services\Translation;

/**
 * Что уходит нейросети. Только имена и тексты: ни id, ни email, ни ключей.
 */
final readonly class TranslationRequest
{
    /**
     * @param string                                    $text         Сообщение, которое переводим
     * @param string                                    $author       Имя его автора
     * @param list<string>                              $targets      Языки, на которые перевести (коды из app.supported_locales)
     * @param list<array{name: string, locale: string}> $participants Участники чата и языки их интерфейса
     * @param list<array{author: string, text: string}> $context      Предыдущие сообщения, от старых к новым: только для понимания
     * @param string|null                               $note         Кто кем друг другу приходится (заметка к чату)
     */
    public function __construct(
        public string $text,
        public string $author,
        public array $targets,
        public array $participants,
        public array $context = [],
        public ?string $note = null,
    ) {
    }
}
