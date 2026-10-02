<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatFolder;
use App\Modules\User\Models\User;
use Tests\TestCase;

final class AddChatToFolderControllerTest extends TestCase
{
    public function testAddsOwnChatOnce(): void
    {
        $me     = $this->actingAsUser();
        $chat   = Chat::factory()->between($me, User::factory()->create())->create();
        $folder = ChatFolder::factory()->ownedBy($me)->create();
        $url    = '/api/chat-folders/' . $folder->id . '/chats/' . $chat->id;

        $this->putJson($url)
            ->assertOk()
            ->assertJsonPath('message', 'Папка сохранена.')
            ->assertJsonPath('data.chat_ids', [$chat->id]);

        $this->putJson($url)->assertOk()->assertJsonPath('data.chat_ids', [$chat->id]);
    }

    public function testRejectsOtherPeopleChats(): void
    {
        $me     = $this->actingAsUser();
        $chat   = Chat::factory()->between(User::factory()->create(), User::factory()->create())->create();
        $folder = ChatFolder::factory()->ownedBy($me)->create();

        $this->putJson('/api/chat-folders/' . $folder->id . '/chats/' . $chat->id)
            ->assertNotFound()
            ->assertJsonPath('message', 'Чат не найден.');
    }

    public function testRejectsOtherPeopleFolders(): void
    {
        $me     = $this->actingAsUser();
        $chat   = Chat::factory()->between($me, User::factory()->create())->create();
        $folder = ChatFolder::factory()->create();

        $this->putJson('/api/chat-folders/' . $folder->id . '/chats/' . $chat->id)
            ->assertNotFound()
            ->assertJsonPath('message', 'Папка не найдена.');
    }
}
