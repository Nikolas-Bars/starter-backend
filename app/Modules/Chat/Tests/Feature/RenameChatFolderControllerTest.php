<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\Models\ChatFolder;
use Tests\TestCase;

final class RenameChatFolderControllerTest extends TestCase
{
    public function testRenamesOwnFolder(): void
    {
        $me     = $this->actingAsUser();
        $folder = ChatFolder::factory()->ownedBy($me)->create(['name' => 'Работа']);

        $this->patchJson('/api/chat-folders/' . $folder->id, ['name' => 'Коллеги'])
            ->assertOk()
            ->assertJsonPath('message', 'Папка сохранена.')
            ->assertJsonPath('data.id', $folder->id)
            ->assertJsonPath('data.name', 'Коллеги');

        self::assertSame('Коллеги', $folder->refresh()->name);
    }

    public function testHidesOtherPeopleFolders(): void
    {
        $this->actingAsUser();
        $folder = ChatFolder::factory()->create(['name' => 'Чужая']);

        $this->patchJson('/api/chat-folders/' . $folder->id, ['name' => 'Моя'])->assertNotFound();

        self::assertSame('Чужая', $folder->refresh()->name);
    }
}
