<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\Actions\CreateChatFolderAction;
use App\Modules\Chat\Models\ChatFolder;
use App\Services\RealtimeBus;
use Tests\TestCase;

final class CreateChatFolderControllerTest extends TestCase
{
    public function testCreatesEmptyFolderAndNotifiesOwnerTabs(): void
    {
        $me = $this->actingAsUser();

        $this->postJson('/api/chat-folders', ['name' => '  Работа  '])
            ->assertCreated()
            ->assertJsonPath('message', 'Папка создана.')
            ->assertJsonPath('data.name', 'Работа')
            ->assertJsonPath('data.chat_ids', [])
            ->assertJsonPath('data.unread_chats_count', 0);

        self::assertTrue(ChatFolder::query()->where('user_id', $me->id)->where('name', 'Работа')->exists());

        $events = $this->app->make(RealtimeBus::class)->drain(10);
        self::assertCount(1, $events);
        self::assertSame([$me->id], $events[0]['user_ids']);
        self::assertSame('chat.folders', $events[0]['message']['type']);
    }

    public function testValidatesName(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/chat-folders', ['name' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->postJson('/api/chat-folders', ['name' => \str_repeat('я', 33)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function testLimitsFolderCount(): void
    {
        $me = $this->actingAsUser();
        ChatFolder::factory()->ownedBy($me)->count(CreateChatFolderAction::MAX_FOLDERS)->create();

        $this->postJson('/api/chat-folders', ['name' => 'Лишняя'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Папок может быть не больше 20.');
    }
}
