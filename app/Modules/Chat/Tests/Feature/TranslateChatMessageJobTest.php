<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\DTO\HeldChatEventDTO;
use App\Modules\Chat\Jobs\DeliverHeldChatEventJob;
use App\Modules\Chat\Jobs\TranslateChatMessageJob;
use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Tasks\QueueChatMessageTranslationTask;
use App\Modules\User\Models\User;
use App\Services\RealtimeBus;
use App\Services\Translation\TranslationFailedException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TranslateChatMessageJobTest extends TestCase
{
    private User $grandson;

    private User $grandma;

    private Chat $chat;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('translation.providers.openai.api_key', 'sk-test-0123456789abcdefghij');

        $this->grandson = User::factory()->create(['name' => 'Минь', 'locale' => 'vi', 'email' => 'minh@example.com']);
        $this->grandma  = User::factory()->create(['name' => 'Анна', 'locale' => 'ru']);
        $this->chat     = Chat::factory()->between($this->grandson, $this->grandma)->create(['translation_note' => 'Бабушка и внук']);
    }

    public function testTranslatesWithContextSavesAndNotifiesMembers(): void
    {
        ChatMessage::factory()->inChat($this->chat, $this->grandma)->create(['body' => 'Как дела в школе?']);
        $message = ChatMessage::factory()->inChat($this->chat, $this->grandson)->create(['body' => 'Con ổn ạ']);
        $this->fakeOpenAi('vi', ['ru' => 'Всё хорошо, бабушка']);

        dispatch(new TranslateChatMessageJob($message->id));

        $message->refresh();
        self::assertSame('vi', $message->body_locale);
        self::assertSame(['ru' => 'Всё хорошо, бабушка'], $message->translations->pluck('body', 'locale')->all());

        Http::assertSent(static function (Request $request): bool {
            $content = (string)$request['messages'][1]['content'];
            $user    = \json_decode($content, true);

            return \is_array($user)
                && $user['relationship'] === 'Бабушка и внук'
                && $user['context'] === [['author' => 'Анна', 'text' => 'Как дела в школе?']]
                && $user['message'] === ['author' => 'Минь', 'text' => 'Con ổn ạ']
                && $user['target_languages'] === [['code' => 'ru', 'name' => 'Russian']]
                && !\str_contains($content, 'minh@example.com');
        });

        $events = $this->app->make(RealtimeBus::class)->drain(10);
        self::assertCount(1, $events);
        self::assertEqualsCanonicalizing([$this->grandson->id, $this->grandma->id], $events[0]['user_ids']);
        self::assertSame('chat.message_translated', $events[0]['message']['type']);
        self::assertSame([
            'chat_id'      => $this->chat->id,
            'message_id'   => $message->id,
            'body_locale'  => 'vi',
            'translations' => ['ru' => 'Всё хорошо, бабушка'],
        ], $events[0]['message']['data']);
    }

    public function testSameLanguageSkipsModel(): void
    {
        $this->grandson->update(['locale' => 'ru']);
        $message = ChatMessage::factory()->inChat($this->chat, $this->grandson)->create(['body' => 'Привет']);
        Http::fake();

        dispatch(new TranslateChatMessageJob($message->id));

        Http::assertNothingSent();
        self::assertNull($message->refresh()->body_locale);
    }

    public function testDiscardsTranslationOfTextEditedMeanwhile(): void
    {
        $message = ChatMessage::factory()->inChat($this->chat, $this->grandson)->create(['body' => 'Con ổn']);
        Http::fake(function () use ($message) {
            $message->newQuery()->whereKey($message->id)->update(['body' => 'Con ổn ạ']);

            return Http::response($this->openAiReply('vi', ['ru' => 'Нормально']));
        });

        dispatch(new TranslateChatMessageJob($message->id));

        self::assertSame(0, $message->translations()->count());
        self::assertSame([], $this->app->make(RealtimeBus::class)->drain(10));
    }

    public function testProviderErrorFailsJobForRetry(): void
    {
        $message = ChatMessage::factory()->inChat($this->chat, $this->grandson)->create(['body' => 'Con ổn ạ']);
        Http::fake(['api.openai.com/*' => Http::response([], 503)]);

        $this->expectException(TranslationFailedException::class);

        dispatch(new TranslateChatMessageJob($message->id));
    }

    public function testSendAndEditQueueTranslationAndHistoryShowsIt(): void
    {
        Queue::fake();
        $this->actingAsUser($this->grandson);

        $id = $this->postJson('/api/chats/' . $this->chat->id . '/messages', ['body' => 'Con ổn', 'client_id' => (string)Str::uuid()])
            ->assertCreated()
            ->assertJsonPath('data.translations', [])
            ->json('data.id');

        Queue::assertPushedOn(QueueChatMessageTranslationTask::QUEUE, TranslateChatMessageJob::class, static fn(TranslateChatMessageJob $job): bool => $job->messageId === $id);

        $message = ChatMessage::query()->findOrFail($id);
        $message->forceFill(['body_locale' => 'vi'])->save();
        $message->translations()->create(['locale' => 'ru', 'body' => 'Нормально']);

        $this->getJson('/api/chats/' . $this->chat->id . '/messages')
            ->assertOk()
            ->assertJsonPath('data.items.0.body_locale', 'vi')
            ->assertJsonPath('data.items.0.translations', ['ru' => 'Нормально']);

        $this->patchJson('/api/chats/' . $this->chat->id . '/messages/' . $id, ['body' => 'Con ổn ạ'])
            ->assertOk()
            ->assertJsonPath('data.body_locale', null)
            ->assertJsonPath('data.translations', []);

        self::assertSame(0, $message->translations()->count());
        Queue::assertPushed(TranslateChatMessageJob::class, 2);
    }

    public function testOtherLanguageGetsMessageAndEditAlreadyTranslated(): void
    {
        Http::fake(['api.openai.com/*' => Http::sequence()
            ->push($this->openAiReply('vi', ['ru' => 'Всё хорошо']))
            ->push($this->openAiReply('vi', ['ru' => 'Всё очень хорошо']))]);
        $this->actingAsUser($this->grandson);

        $id = $this->postJson('/api/chats/' . $this->chat->id . '/messages', ['body' => 'Con ổn ạ', 'client_id' => (string)Str::uuid()])
            ->assertCreated()
            ->json('data.id');

        $events = $this->app->make(RealtimeBus::class)->drain(10);
        self::assertSame(['chat.message', 'chat.message', 'chat.message_translated'], \array_column(\array_column($events, 'message'), 'type'));
        self::assertSame([$this->grandson->id], $events[0]['user_ids']);
        self::assertSame([], $events[0]['message']['data']['message']['translations']);
        self::assertSame([$this->grandma->id], $events[1]['user_ids']);
        self::assertSame($id, $events[1]['message']['data']['message']['id']);
        self::assertSame('vi', $events[1]['message']['data']['message']['body_locale']);
        self::assertSame(['ru' => 'Всё хорошо'], $events[1]['message']['data']['message']['translations']);
        self::assertSame([$this->grandson->id], $events[2]['user_ids']);

        $this->patchJson('/api/chats/' . $this->chat->id . '/messages/' . $id, ['body' => 'Con rất ổn ạ'])->assertOk();

        $events = $this->app->make(RealtimeBus::class)->drain(10);
        self::assertSame(['chat.message_updated', 'chat.message_updated', 'chat.message_translated'], \array_column(\array_column($events, 'message'), 'type'));
        self::assertSame([$this->grandma->id], $events[1]['user_ids']);
        self::assertSame($this->chat->id, $events[1]['message']['data']['chat_id']);
        self::assertSame('Con rất ổn ạ', $events[1]['message']['data']['message']['body']);
        self::assertSame(['ru' => 'Всё очень хорошо'], $events[1]['message']['data']['message']['translations']);
    }

    public function testSameLanguageGetsMessageAtOnce(): void
    {
        $this->grandson->update(['locale' => 'ru']);
        Http::fake();
        $this->actingAsUser($this->grandson);

        $this->postJson('/api/chats/' . $this->chat->id . '/messages', ['body' => 'Привет', 'client_id' => (string)Str::uuid()])->assertCreated();

        $events = $this->app->make(RealtimeBus::class)->drain(10);
        self::assertCount(1, $events);
        self::assertSame('chat.message', $events[0]['message']['type']);
        self::assertEqualsCanonicalizing([$this->grandson->id, $this->grandma->id], $events[0]['user_ids']);
        Http::assertNothingSent();
    }

    public function testProviderErrorDeliversOriginalAtOnceAndRetryTranslates(): void
    {
        $message = ChatMessage::factory()->inChat($this->chat, $this->grandson)->create(['body' => 'Con ổn ạ']);
        $held    = new HeldChatEventDTO('chat.message', [$this->grandma->id], 'held-1');
        Http::fake(['api.openai.com/*' => Http::sequence()
            ->push([], 503)
            ->push($this->openAiReply('vi', ['ru' => 'Всё хорошо']))]);

        try {
            dispatch(new TranslateChatMessageJob($message->id, $held));
            self::fail('Ошибка провайдера должна дойти до очереди');
        } catch (TranslationFailedException) {
        }

        $events = $this->app->make(RealtimeBus::class)->drain(10);
        self::assertCount(1, $events);
        self::assertSame([$this->grandma->id], $events[0]['user_ids']);
        self::assertSame('chat.message', $events[0]['message']['type']);
        self::assertSame('Con ổn ạ', $events[0]['message']['data']['message']['body']);
        self::assertSame([], $events[0]['message']['data']['message']['translations']);

        dispatch(new TranslateChatMessageJob($message->id, $held));

        $events = $this->app->make(RealtimeBus::class)->drain(10);
        self::assertCount(1, $events);
        self::assertSame('chat.message_translated', $events[0]['message']['type']);
        self::assertEqualsCanonicalizing([$this->grandson->id, $this->grandma->id], $events[0]['user_ids']);
    }

    public function testTimerDeliversOriginalOnceAndTranslationFollows(): void
    {
        $message = ChatMessage::factory()->inChat($this->chat, $this->grandson)->create(['body' => 'Con ổn ạ']);
        $held    = new HeldChatEventDTO('chat.message', [$this->grandma->id], 'held-2');
        $this->fakeOpenAi('vi', ['ru' => 'Всё хорошо']);

        dispatch(new DeliverHeldChatEventJob($message->id, $held));
        dispatch(new DeliverHeldChatEventJob($message->id, $held));

        $events = $this->app->make(RealtimeBus::class)->drain(10);
        self::assertCount(1, $events);
        self::assertSame([$this->grandma->id], $events[0]['user_ids']);
        self::assertSame([], $events[0]['message']['data']['message']['translations']);

        dispatch(new TranslateChatMessageJob($message->id, $held));

        $events = $this->app->make(RealtimeBus::class)->drain(10);
        self::assertCount(1, $events);
        self::assertSame('chat.message_translated', $events[0]['message']['type']);
        self::assertEqualsCanonicalizing([$this->grandson->id, $this->grandma->id], $events[0]['user_ids']);
    }

    public function testTimerIsQueuedWithHoldDelay(): void
    {
        Config::set('translation.hold_seconds', 7);
        Queue::fake();
        $this->actingAsUser($this->grandson);

        $this->postJson('/api/chats/' . $this->chat->id . '/messages', ['body' => 'Con ổn', 'client_id' => (string)Str::uuid()])->assertCreated();

        Queue::assertPushed(TranslateChatMessageJob::class, fn(TranslateChatMessageJob $job): bool => $job->held?->user_ids === [$this->grandma->id]);
        Queue::assertPushed(DeliverHeldChatEventJob::class, static fn(DeliverHeldChatEventJob $job): bool => $job->delay === 7);
        self::assertSame([$this->grandson->id], $this->app->make(RealtimeBus::class)->drain(10)[0]['user_ids']);
    }

    public function testNothingQueuedWithoutKey(): void
    {
        Config::set('translation.providers.openai.api_key', '');
        Queue::fake();
        $this->actingAsUser($this->grandson);

        $this->postJson('/api/chats/' . $this->chat->id . '/messages', ['body' => 'Con ổn', 'client_id' => (string)Str::uuid()])->assertCreated();

        Queue::assertNothingPushed();
    }

    /**
     * @param array<string, string> $translations
     */
    private function fakeOpenAi(string $source, array $translations): void
    {
        Http::fake(['api.openai.com/*' => Http::response($this->openAiReply($source, $translations))]);
    }

    /**
     * @param array<string, string> $translations
     *
     * @return array<string, mixed>
     */
    private function openAiReply(string $source, array $translations): array
    {
        $items = [];

        foreach ($translations as $locale => $text) {
            $items[] = ['language' => $locale, 'text' => $text];
        }

        $content = \json_encode(['source_language' => $source, 'translations' => $items], JSON_THROW_ON_ERROR);

        return ['choices' => [['message' => ['content' => $content]]]];
    }
}
