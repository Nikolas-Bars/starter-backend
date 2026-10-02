<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatFolder;
use App\Modules\User\Models\User;
use Tests\TestCase;

final class RemoveChatFromFolderControllerTest extends TestCase
{
    public function testRemovesChatFromFolder(): void
    {
        $me     = $this->actingAsUser();
        $first  = Chat::factory()->between($me, User::factory()->create())->create();
        $second = Chat::factory()->between($me, User::factory()->create())->create();
        $folder = ChatFolder::factory()->ownedBy($me)->withChats($first, $second)->create();

        $this->deleteJson('/api/chat-folders/' . $folder->id . '/chats/' . $first->id)
            ->assertOk()
            ->assertJsonPath('data.chat_ids', [$second->id]);

        self::assertTrue(Chat::query()->whereKey($first->id)->exists());
    }

    public function testHidesOtherPeopleFolders(): void
    {
        $me     = $this->actingAsUser();
        $chat   = Chat::factory()->between($me, User::factory()->create())->create();
        $folder = ChatFolder::factory()->withChats($chat)->create();

        $this->deleteJson('/api/chat-folders/' . $folder->id . '/chats/' . $chat->id)->assertNotFound();

        self::assertSame(1, $folder->chats()->count());
    }
}
