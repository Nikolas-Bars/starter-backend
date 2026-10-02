<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatFolder;
use App\Modules\User\Models\User;
use Tests\TestCase;

final class DeleteChatFolderControllerTest extends TestCase
{
    public function testDeletesFolderButKeepsChats(): void
    {
        $me     = $this->actingAsUser();
        $chat   = Chat::factory()->between($me, User::factory()->create())->create();
        $folder = ChatFolder::factory()->ownedBy($me)->withChats($chat)->create();

        $this->deleteJson('/api/chat-folders/' . $folder->id)
            ->assertOk()
            ->assertJsonPath('message', 'Папка удалена.');

        self::assertFalse(ChatFolder::query()->whereKey($folder->id)->exists());
        self::assertTrue(Chat::query()->whereKey($chat->id)->exists());
    }

    public function testHidesOtherPeopleFolders(): void
    {
        $this->actingAsUser();
        $folder = ChatFolder::factory()->create();

        $this->deleteJson('/api/chat-folders/' . $folder->id)->assertNotFound();

        self::assertTrue(ChatFolder::query()->whereKey($folder->id)->exists());
    }
}
