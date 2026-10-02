<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatFolder;
use App\Modules\Chat\Models\ChatMember;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\User\Models\User;
use Tests\TestCase;

final class ListChatsControllerTest extends TestCase
{
    public function testListsChatsWithMessagesNewestFirst(): void
    {
        $me    = $this->actingAsUser();
        $ivan  = User::factory()->create(['name' => 'Иван']);
        $maria = User::factory()->create(['name' => 'Мария']);
        $oleg  = User::factory()->create(['name' => 'Олег']);

        $withIvan  = Chat::factory()->between($me, $ivan)->create();
        $withMaria = Chat::factory()->between($me, $maria)->create();
        Chat::factory()->between($me, $oleg)->create();

        ChatMessage::factory()->inChat($withMaria, $maria)->create();
        ChatMessage::factory()->inChat($withIvan, $ivan)->create(['body' => 'Первое']);
        $last = ChatMessage::factory()->inChat($withIvan, $ivan)->create(['body' => 'Второе']);

        $this->getJson('/api/chats')
            ->assertOk()
            ->assertJsonPath('message', 'Список чатов.')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.id', $withIvan->id)
            ->assertJsonPath('data.items.0.peer.name', 'Иван')
            ->assertJsonPath('data.items.0.last_message.id', $last->id)
            ->assertJsonPath('data.items.0.last_message.body', 'Второе')
            ->assertJsonPath('data.items.0.unread_count', 2)
            ->assertJsonPath('data.items.1.peer.name', 'Мария');
    }

    public function testCountsOnlyPeerMessagesAfterReadMarker(): void
    {
        $me   = $this->actingAsUser();
        $ivan = User::factory()->create();
        $chat = Chat::factory()->between($me, $ivan)->create();

        $read = ChatMessage::factory()->inChat($chat, $ivan)->create();
        ChatMessage::factory()->inChat($chat, $me)->create();
        $mine = ChatMessage::factory()->inChat($chat, $me)->create();
        ChatMessage::factory()->inChat($chat, $ivan)->create();

        ChatMember::query()->where('chat_id', $chat->id)->where('user_id', $me->id)->update(['last_read_message_id' => $read->id]);
        ChatMember::query()->where('chat_id', $chat->id)->where('user_id', $ivan->id)->update(['last_read_message_id' => $mine->id]);

        $this->getJson('/api/chats')
            ->assertOk()
            ->assertJsonPath('data.items.0.unread_count', 1)
            ->assertJsonPath('data.items.0.last_read_message_id', $read->id)
            ->assertJsonPath('data.items.0.peer_last_read_message_id', $mine->id);
    }

    public function testFiltersByOwnFolder(): void
    {
        $me        = $this->actingAsUser();
        $ivan      = User::factory()->create();
        $maria     = User::factory()->create();
        $withIvan  = Chat::factory()->between($me, $ivan)->create();
        $withMaria = Chat::factory()->between($me, $maria)->create();
        ChatMessage::factory()->inChat($withIvan, $ivan)->create();
        ChatMessage::factory()->inChat($withMaria, $maria)->create();

        $folder = ChatFolder::factory()->ownedBy($me)->withChats($withIvan)->create();
        // Чужая папка с тем же чатом не влияет на выборку
        ChatFolder::factory()->ownedBy($maria)->withChats($withMaria)->create();

        $this->getJson('/api/chats?folder_id=' . $folder->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $withIvan->id);
    }

    public function testRejectsOtherPeopleFolder(): void
    {
        $this->actingAsUser();
        $folder = ChatFolder::factory()->create();

        $this->getJson('/api/chats?folder_id=' . $folder->id)
            ->assertNotFound()
            ->assertJsonPath('message', 'Папка не найдена.');
    }

    public function testDoesNotShowOtherPeopleChats(): void
    {
        $this->actingAsUser();
        $ivan  = User::factory()->create();
        $maria = User::factory()->create();
        $chat  = Chat::factory()->between($ivan, $maria)->create();
        ChatMessage::factory()->inChat($chat, $ivan)->create();

        $this->getJson('/api/chats')->assertOk()->assertJsonCount(0, 'data.items');
    }

    public function testIsClosedForGuestsAndAnonymous(): void
    {
        $this->getJson('/api/chats')->assertUnauthorized();

        $this->actingAsUser(User::factory()->create(['guest_of_id' => User::factory()->create()->id]));

        $this->getJson('/api/chats')->assertForbidden();
    }
}
