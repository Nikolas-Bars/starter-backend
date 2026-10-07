<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\Models\Chat;
use App\Modules\User\Models\User;
use App\Services\RealtimeBus;
use Tests\TestCase;

final class UpdateChatTranslationNoteControllerTest extends TestCase
{
    public function testAnyMemberSetsNoteAndOthersAreNotified(): void
    {
        $me   = $this->actingAsUser();
        $anna = User::factory()->create();
        $chat = Chat::factory()->between($me, $anna)->create();

        $this->putJson($this->url($chat), ['note' => '  Бабушка и внук  '])
            ->assertOk()
            ->assertJsonPath('message', 'Заметка для перевода сохранена.')
            ->assertJsonPath('data.id', $chat->id)
            ->assertJsonPath('data.translation_note', 'Бабушка и внук');

        self::assertSame('Бабушка и внук', $chat->refresh()->translation_note);

        $events = $this->app->make(RealtimeBus::class)->drain(10);
        self::assertCount(1, $events);
        self::assertEqualsCanonicalizing([$me->id, $anna->id], $events[0]['user_ids']);
        self::assertSame('chat.translation_note', $events[0]['message']['type']);
        self::assertSame(['chat_id' => $chat->id, 'translation_note' => 'Бабушка и внук'], $events[0]['message']['data']);
    }

    public function testEmptyNoteClearsItAndSameNoteIsSilent(): void
    {
        $me   = $this->actingAsUser();
        $chat = Chat::factory()->between($me, User::factory()->create())->create(['translation_note' => 'Коллеги']);
        $bus  = $this->app->make(RealtimeBus::class);

        $this->putJson($this->url($chat), ['note' => ' '])->assertOk()->assertJsonPath('data.translation_note', null);
        self::assertNull($chat->refresh()->translation_note);
        $bus->drain(10);

        $this->putJson($this->url($chat), ['note' => null])->assertOk();
        self::assertSame([], $bus->drain(10));
    }

    public function testHidesOtherPeopleChats(): void
    {
        $this->actingAsUser();
        $chat = Chat::factory()->between(User::factory()->create(), User::factory()->create())->create();

        $this->putJson($this->url($chat), ['note' => 'Друзья'])->assertNotFound();
        self::assertNull($chat->refresh()->translation_note);
    }

    public function testValidatesNote(): void
    {
        $me   = $this->actingAsUser();
        $chat = Chat::factory()->between($me, User::factory()->create())->create();

        $this->putJson($this->url($chat), ['note' => \str_repeat('а', 501)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');

        $this->putJson($this->url($chat), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');
    }

    private function url(Chat $chat): string
    {
        return "/api/chats/{$chat->id}/translation-note";
    }
}
