<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Call\Models\Call;
use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatAttachment;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Models\ChatMessageReaction;
use App\Modules\User\Models\User;
use App\Services\RealtimeBus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DeleteChatMessageControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('attachments');
    }

    public function testDeletesLastMessageWithFilesAndNotifiesMembers(): void
    {
        $me       = $this->actingAsUser();
        $ivan     = User::factory()->create();
        $chat     = Chat::factory()->between($me, $ivan)->create();
        $previous = ChatMessage::factory()->inChat($chat, $ivan)->create(['body' => 'Привет']);
        $message  = ChatMessage::factory()->inChat($chat, $me)->create(['body' => 'Фото']);
        $message->reactions()->create(['user_id' => $ivan->id, 'emoji' => '👍']);
        $photo = ChatAttachment::factory()->image()->create(['user_id' => $me->id, 'message_id' => $message->id, 'thumb_path' => 'thumbs/a.jpg']);
        Storage::disk('attachments')->put($photo->path, 'jpeg');
        Storage::disk('attachments')->put('thumbs/a.jpg', 'jpeg');

        $this->deleteJson($this->url($chat, $message))
            ->assertOk()
            ->assertJsonPath('message', 'Сообщение удалено.');

        self::assertNull(ChatMessage::query()->find($message->id));
        self::assertNull(ChatAttachment::query()->find($photo->id));
        self::assertSame(0, ChatMessageReaction::query()->count());
        self::assertSame($previous->id, $chat->refresh()->last_message_id);
        Storage::disk('attachments')->assertMissing([$photo->path, 'thumbs/a.jpg']);

        $events = $this->app->make(RealtimeBus::class)->drain(10);
        self::assertCount(1, $events);
        self::assertEqualsCanonicalizing([$me->id, $ivan->id], $events[0]['user_ids']);
        self::assertSame('chat.message_deleted', $events[0]['message']['type']);
        $data = $events[0]['message']['data'];
        self::assertSame([$chat->id, $message->id, $me->id, true], [$data['chat_id'], $data['message_id'], $data['user_id'], $data['last_changed']]);
        self::assertSame($previous->id, $data['last_message']['id']);
    }

    public function testDeletingOnlyMessageLeavesChatEmpty(): void
    {
        $me      = $this->actingAsUser();
        $chat    = Chat::factory()->between($me, User::factory()->create())->create();
        $message = ChatMessage::factory()->inChat($chat, $me)->create();

        $this->deleteJson($this->url($chat, $message))->assertOk();

        self::assertNull($chat->refresh()->last_message_id);
        $data = $this->app->make(RealtimeBus::class)->drain(10)[0]['message']['data'];
        self::assertTrue($data['last_changed']);
        self::assertNull($data['last_message']);
    }

    public function testDeletingOlderMessageKeepsLastMessage(): void
    {
        $me     = $this->actingAsUser();
        $chat   = Chat::factory()->between($me, $ivan = User::factory()->create())->create();
        $older  = ChatMessage::factory()->inChat($chat, $me)->create();
        $latest = ChatMessage::factory()->inChat($chat, $ivan)->create();

        $this->deleteJson($this->url($chat, $older))->assertOk();

        self::assertSame($latest->id, $chat->refresh()->last_message_id);
        self::assertFalse($this->app->make(RealtimeBus::class)->drain(10)[0]['message']['data']['last_changed']);
    }

    public function testCannotDeletePeerOrCallMessage(): void
    {
        $me   = $this->actingAsUser();
        $ivan = User::factory()->create();
        $chat = Chat::factory()->between($me, $ivan)->create();
        $peer = ChatMessage::factory()->inChat($chat, $ivan)->create();
        $call = ChatMessage::factory()->inChat($chat, $me)->create([
            'type'    => 'call',
            'call_id' => Call::factory()->create(['caller_id' => $me->id, 'callee_id' => $ivan->id])->id,
            'body'    => '',
        ]);

        $this->deleteJson($this->url($chat, $peer))
            ->assertForbidden()
            ->assertJsonPath('message', 'Удалить можно только своё сообщение.');
        $this->deleteJson($this->url($chat, $call))->assertForbidden();

        self::assertSame(2, ChatMessage::query()->count());
    }

    public function testHidesForeignChatsAndMessages(): void
    {
        $this->actingAsUser();
        $strangers = Chat::factory()->between(User::factory()->create(), $author = User::factory()->create())->create();
        $message   = ChatMessage::factory()->inChat($strangers, $author)->create();

        $this->deleteJson($this->url($strangers, $message))->assertNotFound();
        self::assertNotNull(ChatMessage::query()->find($message->id));
    }

    public function testRequiresAuthentication(): void
    {
        $this->deleteJson('/api/chats/1/messages/1')->assertUnauthorized();
    }

    private function url(Chat $chat, ChatMessage $message): string
    {
        return "/api/chats/{$chat->id}/messages/{$message->id}";
    }
}
