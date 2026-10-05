<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Call\Models\Call;
use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\User\Models\User;
use App\Services\RealtimeBus;
use Tests\TestCase;

final class EditChatMessageControllerTest extends TestCase
{
    public function testEditsOwnMessageAndNotifiesMembers(): void
    {
        $me      = $this->actingAsUser();
        $ivan    = User::factory()->create();
        $chat    = Chat::factory()->between($me, $ivan)->create();
        $message = ChatMessage::factory()->inChat($chat, $me)->create(['body' => 'Привт']);
        $message->reactions()->create(['user_id' => $ivan->id, 'emoji' => '👍']);
        ChatMessage::factory()->inChat($chat, $me)->create(['body' => 'Ещё моё']);

        $this->patchJson($this->url($chat, $message), ['body' => '  Привет  '])
            ->assertOk()
            ->assertJsonPath('message', 'Сообщение изменено.')
            ->assertJsonPath('data.body', 'Привет')
            ->assertJsonPath('data.reactions.0.emoji', '👍')
            ->assertJsonPath('data.edited_at', fn(mixed $value): bool => \is_string($value));

        self::assertSame('Привет', $message->refresh()->body);
        self::assertNotNull($message->edited_at);

        $events = $this->app->make(RealtimeBus::class)->drain(10);
        self::assertCount(1, $events);
        self::assertEqualsCanonicalizing([$me->id, $ivan->id], $events[0]['user_ids']);
        self::assertSame('chat.message_updated', $events[0]['message']['type']);
        self::assertSame($chat->id, $events[0]['message']['data']['chat_id']);
        self::assertSame('Привет', $events[0]['message']['data']['message']['body']);
    }

    public function testSameTextChangesNothing(): void
    {
        $me      = $this->actingAsUser();
        $chat    = Chat::factory()->between($me, User::factory()->create())->create();
        $message = ChatMessage::factory()->inChat($chat, $me)->create(['body' => 'Привет']);

        $this->patchJson($this->url($chat, $message), ['body' => 'Привет'])
            ->assertOk()
            ->assertJsonPath('data.edited_at', null);

        self::assertSame([], $this->app->make(RealtimeBus::class)->drain(10));
    }

    public function testCannotEditAnsweredMessage(): void
    {
        $me      = $this->actingAsUser();
        $ivan    = User::factory()->create();
        $chat    = Chat::factory()->between($me, $ivan)->create();
        $message = ChatMessage::factory()->inChat($chat, $me)->create(['body' => 'Привет']);
        ChatMessage::factory()->inChat($chat, $ivan)->create();

        $this->patchJson($this->url($chat, $message), ['body' => 'Пока'])
            ->assertConflict()
            ->assertJsonPath('message', 'На сообщение уже ответили — изменить его нельзя.');

        self::assertSame('Привет', $message->refresh()->body);
    }

    public function testPeerCallCountsAsAnswer(): void
    {
        $me      = $this->actingAsUser();
        $ivan    = User::factory()->create();
        $chat    = Chat::factory()->between($me, $ivan)->create();
        $message = ChatMessage::factory()->inChat($chat, $me)->create();
        ChatMessage::factory()->inChat($chat, $ivan)->create([
            'type'    => 'call',
            'call_id' => Call::factory()->create(['caller_id' => $ivan->id, 'callee_id' => $me->id])->id,
            'body'    => '',
        ]);

        $this->patchJson($this->url($chat, $message), ['body' => 'Пока'])->assertConflict();
    }

    public function testCannotEditPeerForwardedOrCallMessage(): void
    {
        $me        = $this->actingAsUser();
        $ivan      = User::factory()->create();
        $chat      = Chat::factory()->between($me, $ivan)->create();
        $peer      = ChatMessage::factory()->inChat($chat, $ivan)->create();
        $forwarded = ChatMessage::factory()->inChat($chat, $me)->create(['forwarded_from_user_id' => $ivan->id, 'forwarded_from_name' => 'Иван']);
        $call      = ChatMessage::factory()->inChat($chat, $me)->create([
            'type'    => 'call',
            'call_id' => Call::factory()->create(['caller_id' => $me->id, 'callee_id' => $ivan->id])->id,
            'body'    => '',
        ]);

        foreach ([$peer, $forwarded, $call] as $message) {
            $this->patchJson($this->url($chat, $message), ['body' => 'Новое'])
                ->assertForbidden()
                ->assertJsonPath('message', 'Изменить можно только своё сообщение.');
        }
    }

    public function testValidatesBody(): void
    {
        $me      = $this->actingAsUser();
        $chat    = Chat::factory()->between($me, User::factory()->create())->create();
        $message = ChatMessage::factory()->inChat($chat, $me)->create();

        $this->patchJson($this->url($chat, $message), ['body' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
        $this->patchJson($this->url($chat, $message), ['body' => \str_repeat('а', 4001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    public function testHidesForeignChats(): void
    {
        $this->actingAsUser();
        $strangers = Chat::factory()->between(User::factory()->create(), $author = User::factory()->create())->create();
        $message   = ChatMessage::factory()->inChat($strangers, $author)->create(['body' => 'Секрет']);

        $this->patchJson($this->url($strangers, $message), ['body' => 'Взлом'])->assertNotFound();
        self::assertSame('Секрет', $message->refresh()->body);
    }

    public function testRequiresAuthentication(): void
    {
        $this->patchJson('/api/chats/1/messages/1', ['body' => 'x'])->assertUnauthorized();
    }

    private function url(Chat $chat, ChatMessage $message): string
    {
        return "/api/chats/{$chat->id}/messages/{$message->id}";
    }
}
