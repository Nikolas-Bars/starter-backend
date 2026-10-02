<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatFolder;
use App\Modules\Chat\Models\ChatMember;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\User\Models\User;
use Tests\TestCase;

final class ListChatFoldersControllerTest extends TestCase
{
    public function testListsOwnFoldersWithChatsAndUnreadChats(): void
    {
        $me    = $this->actingAsUser();
        $ivan  = User::factory()->create();
        $maria = User::factory()->create();
        $read  = Chat::factory()->between($me, $ivan)->create();
        $fresh = Chat::factory()->between($me, $maria)->create();

        $readMessage = ChatMessage::factory()->inChat($read, $ivan)->create();
        ChatMember::query()->where('chat_id', $read->id)->where('user_id', $me->id)->update(['last_read_message_id' => $readMessage->id]);
        ChatMessage::factory()->inChat($fresh, $maria)->count(2)->create();

        $work    = ChatFolder::factory()->ownedBy($me)->withChats($read, $fresh)->create(['name' => 'Работа']);
        $friends = ChatFolder::factory()->ownedBy($me)->create(['name' => 'Друзья']);
        ChatFolder::factory()->ownedBy($ivan)->withChats($read)->create();

        $this->getJson('/api/chat-folders')
            ->assertOk()
            ->assertJsonPath('message', 'Папки чатов.')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $work->id)
            ->assertJsonPath('data.0.name', 'Работа')
            ->assertJsonPath('data.0.chat_ids', [$read->id, $fresh->id])
            ->assertJsonPath('data.0.unread_chats_count', 1)
            ->assertJsonPath('data.1.id', $friends->id)
            ->assertJsonPath('data.1.chat_ids', [])
            ->assertJsonPath('data.1.unread_chats_count', 0);
    }

    public function testGuestsHaveNoFolders(): void
    {
        $this->actingAsUser(User::factory()->create(['guest_of_id' => User::factory()->create()->id]));

        $this->getJson('/api/chat-folders')->assertForbidden();
    }
}
