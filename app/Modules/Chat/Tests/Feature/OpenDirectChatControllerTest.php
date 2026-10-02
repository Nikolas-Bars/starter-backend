<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatMember;
use App\Modules\User\Models\User;
use Tests\TestCase;

final class OpenDirectChatControllerTest extends TestCase
{
    public function testCreatesChatOnceForBothSides(): void
    {
        $me   = User::factory()->create();
        $ivan = User::factory()->create(['name' => 'Иван']);

        $this->actingAsUser($me);
        $chatId = $this->postJson('/api/chats/direct', ['user_id' => $ivan->id])
            ->assertOk()
            ->assertJsonPath('message', 'Чат открыт.')
            ->assertJsonPath('data.peer.name', 'Иван')
            ->assertJsonPath('data.last_message', null)
            ->assertJsonPath('data.unread_count', 0)
            ->json('data.id');

        $this->postJson('/api/chats/direct', ['user_id' => $ivan->id])->assertOk()->assertJsonPath('data.id', $chatId);

        $this->actingAsUser($ivan);
        $this->postJson('/api/chats/direct', ['user_id' => $me->id])->assertOk()->assertJsonPath('data.id', $chatId);

        self::assertSame(1, Chat::query()->count());
        self::assertSame(2, ChatMember::query()->where('chat_id', $chatId)->count());
    }

    public function testRejectsSelfGuestsAndUnknownUsers(): void
    {
        $me    = $this->actingAsUser();
        $guest = User::factory()->create(['guest_of_id' => $me->id]);

        foreach ([$me->id, $guest->id, 999999] as $peerId) {
            $this->postJson('/api/chats/direct', ['user_id' => $peerId])
                ->assertUnprocessable()
                ->assertJsonPath('message', 'Написать этому пользователю нельзя.');
        }

        self::assertSame(0, Chat::query()->count());
    }

    public function testValidatesUserId(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/chats/direct', [])->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->postJson('/api/chats/direct', ['user_id' => 'ivan'])->assertUnprocessable()->assertJsonValidationErrors('user_id');
    }

    public function testIsClosedForGuests(): void
    {
        $host = User::factory()->create();
        $this->actingAsUser(User::factory()->create(['guest_of_id' => $host->id]));

        $this->postJson('/api/chats/direct', ['user_id' => $host->id])->assertForbidden();
    }
}
