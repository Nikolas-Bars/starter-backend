<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\User\Models\User;
use Tests\TestCase;

final class ShowChatControllerTest extends TestCase
{
    public function testShowsChatOfMember(): void
    {
        $me   = $this->actingAsUser();
        $ivan = User::factory()->create(['name' => 'Иван', 'username' => 'ivan']);
        $chat = Chat::factory()->between($me, $ivan)->create();
        ChatMessage::factory()->inChat($chat, $ivan)->create(['body' => 'Привет']);

        $this->getJson('/api/chats/' . $chat->id)
            ->assertOk()
            ->assertJsonPath('data.id', $chat->id)
            ->assertJsonPath('data.type', 'direct')
            ->assertJsonPath('data.peer.username', 'ivan')
            ->assertJsonPath('data.last_message.body', 'Привет')
            ->assertJsonPath('data.unread_count', 1);
    }

    public function testHidesChatsOfOthers(): void
    {
        $this->actingAsUser();
        $chat = Chat::factory()->between(User::factory()->create(), User::factory()->create())->create();

        $this->getJson('/api/chats/' . $chat->id)
            ->assertNotFound()
            ->assertJsonPath('message', 'Чат не найден.');

        $this->getJson('/api/chats/999999')->assertNotFound();
        $this->getJson('/api/chats/abc')->assertNotFound();
    }
}
