<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Unit;

use App\Services\Secret;
use App\Services\Translation\AnthropicTranslator;
use App\Services\Translation\OpenAiTranslator;
use App\Services\Translation\TranslationFailedException;
use App\Services\Translation\TranslationPrompt;
use App\Services\Translation\TranslationRequest;
use App\Services\Translation\TranslatorFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class TranslatorTest extends TestCase
{
    private const string KEY = 'sk-test-0123456789abcdefghij';

    public function testOpenAiSendsKeyInHeaderAndDoesNotStoreConversation(): void
    {
        Http::fake(['api.openai.com/*' => Http::response($this->openAiReply(['source_language' => 'vi', 'translations' => [['language' => 'ru', 'text' => 'Привет, бабушка']]]))]);

        $result = $this->openAi()->translate($this->request());

        self::assertSame('vi', $result->sourceLocale);
        self::assertSame(['ru' => 'Привет, бабушка'], $result->translations);

        Http::assertSent(static function (Request $request): bool {
            $user = \json_decode((string)$request['messages'][1]['content'], true);

            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer ' . self::KEY)
                && !\str_contains($request->url(), self::KEY)
                && $request['store'] === false
                && $request['response_format']['json_schema']['strict'] === true
                && \is_array($user)
                && $user['relationship'] === 'Бабушка и внук'
                && $user['message']['text'] === 'Chào bà'
                && $user['context'][0]['text'] === 'Привет, внучек';
        });
    }

    public function testAnthropicReadsForcedToolCall(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [
            ['type' => 'tool_use', 'name' => 'submit_translation', 'input' => ['source_language' => 'vi', 'translations' => [['language' => 'ru', 'text' => 'Привет, бабушка']]]],
        ]])]);

        $translator = new AnthropicTranslator(new Secret(self::KEY), 'claude-haiku-4-5', 'https://api.anthropic.com/v1', 5);
        $result     = $translator->translate($this->request());

        self::assertSame(['ru' => 'Привет, бабушка'], $result->translations);

        Http::assertSent(static fn(Request $request): bool => $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', self::KEY)
            && $request['tool_choice'] === ['type' => 'tool', 'name' => 'submit_translation']);
    }

    public function testErrorTellsStatusButNotKeyOrResponseBody(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key provided: ' . self::KEY]], 401)]);

        try {
            $this->openAi()->translate($this->request());
            self::fail('Ожидалась ошибка перевода');
        } catch (TranslationFailedException $exception) {
            self::assertSame('openai: HTTP 401', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testDropsUnrequestedLanguagesAndSourceLanguage(): void
    {
        $result = TranslationPrompt::fromArray('test', [
            'source_language' => 'VI',
            'translations'    => [
                ['language' => 'ru', 'text' => ' Привет '],
                ['language' => 'vi', 'text' => 'Chào'],
                ['language' => 'de', 'text' => 'Hallo'],
            ],
        ], $this->request(['ru', 'vi']));

        self::assertSame('vi', $result->sourceLocale);
        self::assertSame(['ru' => 'Привет'], $result->translations);
    }

    public function testMissingTranslationFails(): void
    {
        $this->expectException(TranslationFailedException::class);

        TranslationPrompt::fromArray('test', ['source_language' => 'vi', 'translations' => []], $this->request());
    }

    public function testMessageAlreadyInTargetLanguageNeedsNoTranslation(): void
    {
        $result = TranslationPrompt::fromArray('test', ['source_language' => 'ru', 'translations' => []], $this->request());

        self::assertSame('ru', $result->sourceLocale);
        self::assertSame([], $result->translations);
    }

    public function testFactoryIsOffWithoutKeyAndPicksConfiguredProvider(): void
    {
        $factory = $this->app->make(TranslatorFactory::class);
        self::assertNull($factory->make());

        Config::set('translation.providers.anthropic.api_key', self::KEY);
        Config::set('translation.provider', 'anthropic');
        self::assertInstanceOf(AnthropicTranslator::class, $factory->make());

        Config::set('translation.provider', 'openai');
        self::assertNull($factory->make());
    }

    public function testEverySupportedLocaleHasLanguageName(): void
    {
        foreach (Config::array('app.supported_locales') as $locale) {
            self::assertArrayHasKey($locale, TranslationPrompt::LANGUAGE_NAMES, "Добавьте название языка {$locale} в TranslationPrompt");
        }
    }

    private function openAi(): OpenAiTranslator
    {
        return new OpenAiTranslator(new Secret(self::KEY), 'gpt-4o-mini', 'https://api.openai.com/v1', 5);
    }

    /**
     * @param list<string> $targets
     */
    private function request(array $targets = ['ru']): TranslationRequest
    {
        return new TranslationRequest(
            text: 'Chào bà',
            author: 'Минь',
            targets: $targets,
            participants: [['name' => 'Минь', 'locale' => 'vi'], ['name' => 'Анна', 'locale' => 'ru']],
            context: [['author' => 'Анна', 'text' => 'Привет, внучек']],
            note: 'Бабушка и внук',
        );
    }

    /**
     * @param array<string, mixed> $content
     *
     * @return array<string, mixed>
     */
    private function openAiReply(array $content): array
    {
        return ['choices' => [['message' => ['content' => \json_encode($content, JSON_THROW_ON_ERROR)]]]];
    }
}
