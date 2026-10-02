<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\User\Models\User;
use App\Services\RealtimeBus;
use Tests\TestCase;

final class MarkChatReadControllerTest extends TestCase
{
    public function testMarksReadAndNotifiesMembers(): void
    {
        $me    = $this->actingAsUser();
        $ivan  = User::factory()->create();
        $chat  = Chat::factory()->between($me, $ivan)->create();
        $first = ChatMessage::factory()->inChat($chat, $ivan)->create();
        ChatMessage::factory()->inChat($chat, $ivan)->create();

        $this->postJson('/api/chats/' . $chat->id . '/read', ['message_id' => $first->id])
            ->assertOk()
            ->assertJsonPath('message', 'Сообщения прочитаны.')
            ->assertJsonPath('data', [
                'chat_id'              => $chat->id,
                'user_id'              => $me->id,
                'last_read_message_id' => $first->id,
                'unread_count'         => 1,
            ]);

        $events = $this->app->make(RealtimeBus::class)->drain(10);
        self::assertCount(1, $events);
        self::assertEqualsCanonicalizing([$me->id, $ivan->id], $events[0]['user_ids']);
        self::assertSame('chat.read', $events[0]['message']['type']);
        self::assertSame($first->id, $events[0]['message']['data']['last_read_message_id']);
    }

    public function testMarkerNeverMovesBackOrPastLastMessage(): void
    {
        $me    = $this->actingAsUser();
        $chat  = Chat::factory()->between($me, $ivan = User::factory()->create())->create();
        $first = ChatMessage::factory()->inChat($chat, $ivan)->create();
        $last  = ChatMessage::factory()->inChat($chat, $ivan)->create();
        $bus   = $this->app->make(RealtimeBus::class);

        $this->postJson('/api/chats/' . $chat->id . '/read', ['message_id' => $last->id + 100])
            ->assertOk()
            ->assertJsonPath('data.last_read_message_id', $last->id)
            ->assertJsonPath('data.unread_count', 0);
        $bus->drain(10);

        $this->postJson('/api/chats/' . $chat->id . '/read', ['message_id' => $first->id])
            ->assertOk()
            ->assertJsonPath('data.last_read_message_id', $last->id);

        self::assertSame([], $bus->drain(10));
    }

    public function testHidesOtherPeopleChats(): void
    {
        $this->actingAsUser();
        $chat = Chat::factory()->between(User::factory()->create(), User::factory()->create())->create();

        $this->postJson('/api/chats/' . $chat->id . '/read', ['message_id' => 1])->assertNotFound();
    }

    public function testValidatesMessageId(): void
    {
        $me   = $this->actingAsUser();
        $chat = Chat::factory()->between($me, User::factory()->create())->create();

        $this->postJson('/api/chats/' . $chat->id . '/read', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message_id');
    }
}
