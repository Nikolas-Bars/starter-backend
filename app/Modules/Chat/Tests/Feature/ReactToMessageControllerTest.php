<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\User\Models\User;
use App\Services\RealtimeBus;
use Tests\TestCase;

final class ReactToMessageControllerTest extends TestCase
{
    public function testReactsAndNotifiesMembers(): void
    {
        $me      = $this->actingAsUser();
        $ivan    = User::factory()->create();
        $chat    = Chat::factory()->between($me, $ivan)->create();
        $message = ChatMessage::factory()->inChat($chat, $ivan)->create();

        $this->putJson($this->url($chat, $message), ['emoji' => '❤️'])
            ->assertOk()
            ->assertJsonPath('message', 'Реакция сохранена.')
            ->assertJsonPath('data.id', $message->id)
            ->assertJsonPath('data.reactions', [['emoji' => '❤️', 'user_ids' => [$me->id]]]);

        $events = $this->app->make(RealtimeBus::class)->drain(10);
        self::assertCount(1, $events);
        self::assertEqualsCanonicalizing([$me->id, $ivan->id], $events[0]['user_ids']);
        self::assertSame('chat.reaction', $events[0]['message']['type']);
        self::assertSame([
            'chat_id'    => $chat->id,
            'message_id' => $message->id,
            'reactions'  => [['emoji' => '❤️', 'user_ids' => [$me->id]]],
        ], $events[0]['message']['data']);
    }

    public function testNewReactionReplacesOwnAndKeepsPeers(): void
    {
        $me      = $this->actingAsUser();
        $ivan    = User::factory()->create();
        $chat    = Chat::factory()->between($me, $ivan)->create();
        $message = ChatMessage::factory()->inChat($chat, $ivan)->create();
        $message->reactions()->create(['user_id' => $ivan->id, 'emoji' => '👍']);

        $this->putJson($this->url($chat, $message), ['emoji' => '🔥'])->assertOk();
        $this->putJson($this->url($chat, $message), ['emoji' => '👍'])
            ->assertOk()
            ->assertJsonPath('data.reactions', [['emoji' => '👍', 'user_ids' => [$ivan->id, $me->id]]]);

        self::assertSame(2, $message->reactions()->count());
    }

    public function testSameReactionAgainChangesNothing(): void
    {
        $me      = $this->actingAsUser();
        $chat    = Chat::factory()->between($me, $ivan = User::factory()->create())->create();
        $message = ChatMessage::factory()->inChat($chat, $ivan)->create();
        $bus     = $this->app->make(RealtimeBus::class);

        $this->putJson($this->url($chat, $message), ['emoji' => '👍'])->assertOk();
        $bus->drain(10);

        $this->putJson($this->url($chat, $message), ['emoji' => '👍'])->assertOk();

        self::assertSame([], $bus->drain(10));
    }

    public function testRejectsUnknownEmoji(): void
    {
        $me      = $this->actingAsUser();
        $chat    = Chat::factory()->between($me, $ivan = User::factory()->create())->create();
        $message = ChatMessage::factory()->inChat($chat, $ivan)->create();

        $this->putJson($this->url($chat, $message), ['emoji' => '🦄'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('emoji');
    }

    public function testMessageMustBelongToChat(): void
    {
        $me      = $this->actingAsUser();
        $chat    = Chat::factory()->between($me, User::factory()->create())->create();
        $other   = Chat::factory()->between(User::factory()->create(), $author = User::factory()->create())->create();
        $message = ChatMessage::factory()->inChat($other, $author)->create();

        $this->putJson($this->url($chat, $message), ['emoji' => '👍'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Сообщение не найдено.');
    }

    public function testHidesOtherPeopleChats(): void
    {
        $this->actingAsUser();
        $chat    = Chat::factory()->between(User::factory()->create(), $author = User::factory()->create())->create();
        $message = ChatMessage::factory()->inChat($chat, $author)->create();

        $this->putJson($this->url($chat, $message), ['emoji' => '👍'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Чат не найден.');
    }

    private function url(Chat $chat, ChatMessage $message): string
    {
        return '/api/chats/' . $chat->id . '/messages/' . $message->id . '/reaction';
    }
}
