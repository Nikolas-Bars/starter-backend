<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\User\Models\User;
use App\Services\RealtimeBus;
use Tests\TestCase;

final class RemoveMessageReactionControllerTest extends TestCase
{
    public function testRemovesOwnReactionOnly(): void
    {
        $me      = $this->actingAsUser();
        $ivan    = User::factory()->create();
        $chat    = Chat::factory()->between($me, $ivan)->create();
        $message = ChatMessage::factory()->inChat($chat, $ivan)->create();
        $message->reactions()->create(['user_id' => $ivan->id, 'emoji' => '👍']);
        $message->reactions()->create(['user_id' => $me->id, 'emoji' => '😂']);

        $this->deleteJson($this->url($chat, $message))
            ->assertOk()
            ->assertJsonPath('message', 'Реакция убрана.')
            ->assertJsonPath('data.reactions', [['emoji' => '👍', 'user_ids' => [$ivan->id]]]);

        $events = $this->app->make(RealtimeBus::class)->drain(10);
        self::assertCount(1, $events);
        self::assertSame('chat.reaction', $events[0]['message']['type']);
        self::assertSame([['emoji' => '👍', 'user_ids' => [$ivan->id]]], $events[0]['message']['data']['reactions']);
    }

    public function testWithoutReactionChangesNothing(): void
    {
        $me      = $this->actingAsUser();
        $chat    = Chat::factory()->between($me, $ivan = User::factory()->create())->create();
        $message = ChatMessage::factory()->inChat($chat, $ivan)->create();

        $this->deleteJson($this->url($chat, $message))
            ->assertOk()
            ->assertJsonPath('data.reactions', []);

        self::assertSame([], $this->app->make(RealtimeBus::class)->drain(10));
    }

    public function testHidesOtherPeopleChats(): void
    {
        $this->actingAsUser();
        $chat    = Chat::factory()->between(User::factory()->create(), $author = User::factory()->create())->create();
        $message = ChatMessage::factory()->inChat($chat, $author)->create();

        $this->deleteJson($this->url($chat, $message))->assertNotFound();
    }

    private function url(Chat $chat, ChatMessage $message): string
    {
        return '/api/chats/' . $chat->id . '/messages/' . $message->id . '/reaction';
    }
}
