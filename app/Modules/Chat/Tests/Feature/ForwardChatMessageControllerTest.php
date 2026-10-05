<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Call\Models\Call;
use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatAttachment;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\User\Models\User;
use App\Services\RealtimeBus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ForwardChatMessageControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('attachments');
    }

    public function testForwardsTextAndCopiesReadyFiles(): void
    {
        $me     = $this->actingAsUser();
        $maria  = User::factory()->create(['name' => 'Мария']);
        $ivan   = User::factory()->create();
        $from   = Chat::factory()->between($me, $maria)->create();
        $to     = Chat::factory()->between($me, $ivan)->create();
        $source = ChatMessage::factory()->inChat($from, $maria)->create(['body' => 'Смотри']);
        $photo  = ChatAttachment::factory()->image()->create(['user_id' => $maria->id, 'message_id' => $source->id]);
        ChatAttachment::factory()->processing()->create(['user_id' => $maria->id, 'message_id' => $source->id]);
        Storage::disk('attachments')->put($photo->path, 'jpeg');
        Storage::disk('attachments')->put((string)$photo->thumb_path, 'thumb');

        $response = $this->postJson($this->url($to), ['message_id' => $source->id, 'client_id' => Str::uuid()->toString()])
            ->assertCreated()
            ->assertJsonPath('message', 'Сообщение переслано.')
            ->assertJsonPath('data.chat_id', $to->id)
            ->assertJsonPath('data.user_id', $me->id)
            ->assertJsonPath('data.body', 'Смотри')
            ->assertJsonPath('data.forwarded_from', ['user_id' => $maria->id, 'name' => 'Мария'])
            ->assertJsonCount(1, 'data.attachments');

        $copy = ChatAttachment::query()->where('message_id', $response->json('data.id'))->sole();
        self::assertNotSame($photo->path, $copy->path);
        self::assertSame($me->id, $copy->user_id);
        Storage::disk('attachments')->assertExists([$copy->path, (string)$copy->thumb_path, $photo->path]);
        self::assertSame($response->json('data.id'), $to->refresh()->last_message_id);

        $events = $this->app->make(RealtimeBus::class)->drain(10);
        self::assertCount(1, $events);
        self::assertEqualsCanonicalizing([$me->id, $ivan->id], $events[0]['user_ids']);
        self::assertSame('chat.message', $events[0]['message']['type']);
    }

    public function testForwardingForwardedKeepsOriginalAuthor(): void
    {
        $me     = $this->actingAsUser();
        $ivan   = User::factory()->create();
        $chat   = Chat::factory()->between($me, $ivan)->create();
        $source = ChatMessage::factory()->inChat($chat, $ivan)->create([
            'body'                   => 'Анекдот',
            'forwarded_from_user_id' => null,
            'forwarded_from_name'    => 'Бабушка',
        ]);

        $this->postJson($this->url($chat), ['message_id' => $source->id, 'client_id' => Str::uuid()->toString()])
            ->assertCreated()
            ->assertJsonPath('data.forwarded_from', ['user_id' => null, 'name' => 'Бабушка']);
    }

    public function testRepeatWithSameClientIdReturnsSameMessage(): void
    {
        $me       = $this->actingAsUser();
        $chat     = Chat::factory()->between($me, $ivan = User::factory()->create())->create();
        $source   = ChatMessage::factory()->inChat($chat, $ivan)->create();
        $clientId = Str::uuid()->toString();

        $first  = $this->postJson($this->url($chat), ['message_id' => $source->id, 'client_id' => $clientId])->json('data.id');
        $second = $this->postJson($this->url($chat), ['message_id' => $source->id, 'client_id' => $clientId])->json('data.id');

        self::assertSame($first, $second);
        self::assertSame(2, ChatMessage::query()->count());
    }

    public function testCannotForwardFromForeignChatOrIntoForeignChat(): void
    {
        $me        = $this->actingAsUser();
        $mine      = Chat::factory()->between($me, User::factory()->create())->create();
        $strangers = Chat::factory()->between(User::factory()->create(), $author = User::factory()->create())->create();
        $foreign   = ChatMessage::factory()->inChat($strangers, $author)->create();
        $own       = ChatMessage::factory()->inChat($mine, $me)->create();

        $this->postJson($this->url($mine), ['message_id' => $foreign->id, 'client_id' => Str::uuid()->toString()])->assertNotFound();
        $this->postJson($this->url($strangers), ['message_id' => $own->id, 'client_id' => Str::uuid()->toString()])->assertNotFound();
    }

    public function testCannotForwardCallMessage(): void
    {
        $me   = $this->actingAsUser();
        $ivan = User::factory()->create();
        $chat = Chat::factory()->between($me, $ivan)->create();
        $call = ChatMessage::factory()->inChat($chat, $me)->create([
            'type'    => 'call',
            'call_id' => Call::factory()->create(['caller_id' => $me->id, 'callee_id' => $ivan->id])->id,
            'body'    => '',
        ]);

        $this->postJson($this->url($chat), ['message_id' => $call->id, 'client_id' => Str::uuid()->toString()])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Это сообщение нельзя переслать.');
    }

    public function testValidatesInput(): void
    {
        $me   = $this->actingAsUser();
        $chat = Chat::factory()->between($me, User::factory()->create())->create();

        $this->postJson($this->url($chat), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message_id', 'client_id']);
    }

    private function url(Chat $chat): string
    {
        return "/api/chats/{$chat->id}/messages/forward";
    }
}
