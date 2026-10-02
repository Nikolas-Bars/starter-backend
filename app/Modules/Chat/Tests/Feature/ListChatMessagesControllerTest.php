<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\User\Models\User;
use Tests\TestCase;

final class ListChatMessagesControllerTest extends TestCase
{
    public function testPagesFromNewestBackwardsInChronologicalOrder(): void
    {
        $me   = $this->actingAsUser();
        $ivan = User::factory()->create();
        $chat = Chat::factory()->between($me, $ivan)->create();

        /** @var list<int> $ids */
        $ids = [];
        for ($index = 1; $index <= 55; ++$index) {
            $ids[] = ChatMessage::factory()->inChat($chat, $index % 2 === 0 ? $me : $ivan)->create(['body' => 'Сообщение ' . $index])->id;
        }

        $first = $this->getJson('/api/chats/' . $chat->id . '/messages')
            ->assertOk()
            ->assertJsonPath('message', 'Сообщения чата.')
            ->assertJsonCount(50, 'data.items')
            ->assertJsonPath('data.items.0.id', $ids[5])
            ->assertJsonPath('data.items.49.id', $ids[54])
            ->assertJsonPath('data.items.49.body', 'Сообщение 55')
            ->assertJsonPath('data.has_more', true);

        $this->getJson('/api/chats/' . $chat->id . '/messages?before_id=' . $first->json('data.items.0.id'))
            ->assertOk()
            ->assertJsonCount(5, 'data.items')
            ->assertJsonPath('data.items.0.id', $ids[0])
            ->assertJsonPath('data.items.4.id', $ids[4])
            ->assertJsonPath('data.has_more', false);
    }

    public function testIncludesReactionsGroupedByEmoji(): void
    {
        $me      = $this->actingAsUser();
        $ivan    = User::factory()->create();
        $chat    = Chat::factory()->between($me, $ivan)->create();
        $message = ChatMessage::factory()->inChat($chat, $ivan)->create();
        $message->reactions()->create(['user_id' => $ivan->id, 'emoji' => '🔥']);
        $message->reactions()->create(['user_id' => $me->id, 'emoji' => '🔥']);

        $this->getJson('/api/chats/' . $chat->id . '/messages')
            ->assertOk()
            ->assertJsonPath('data.items.0.reactions', [['emoji' => '🔥', 'user_ids' => [$ivan->id, $me->id]]]);
    }

    public function testHidesMessagesOfOtherChats(): void
    {
        $this->actingAsUser();
        $ivan = User::factory()->create();
        $chat = Chat::factory()->between($ivan, User::factory()->create())->create();
        ChatMessage::factory()->inChat($chat, $ivan)->create();

        $this->getJson('/api/chats/' . $chat->id . '/messages')->assertNotFound();
    }

    public function testValidatesBeforeId(): void
    {
        $me   = $this->actingAsUser();
        $chat = Chat::factory()->between($me, User::factory()->create())->create();

        $this->getJson('/api/chats/' . $chat->id . '/messages?before_id=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('before_id');
    }
}
