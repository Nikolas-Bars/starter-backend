<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\Enums\ChatAttachmentStatusEnum;
use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatAttachment;
use App\Modules\Chat\Models\ChatMember;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\User\Models\User;
use App\Services\RealtimeBus;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SendChatMessageControllerTest extends TestCase
{
    public function testSavesMessageAndNotifiesMembers(): void
    {
        $me       = $this->actingAsUser();
        $ivan     = User::factory()->create();
        $chat     = Chat::factory()->between($me, $ivan)->create();
        $clientId = Str::uuid()->toString();

        $response = $this->postJson('/api/chats/' . $chat->id . '/messages', ['body' => '  Привет!  ', 'client_id' => $clientId])
            ->assertCreated()
            ->assertJsonPath('message', 'Сообщение отправлено.')
            ->assertJsonPath('data.chat_id', $chat->id)
            ->assertJsonPath('data.user_id', $me->id)
            ->assertJsonPath('data.client_id', $clientId)
            ->assertJsonPath('data.body', 'Привет!');

        $messageId = $response->json('data.id');
        self::assertIsInt($messageId);
        self::assertSame($messageId, $chat->refresh()->last_message_id);
        self::assertSame($messageId, $this->member($chat, $me)->last_read_message_id);
        self::assertSame(0, $this->member($chat, $ivan)->last_read_message_id);

        $events = $this->app->make(RealtimeBus::class)->drain(10);
        self::assertCount(1, $events);
        self::assertEqualsCanonicalizing([$me->id, $ivan->id], $events[0]['user_ids']);
        self::assertSame('chat.message', $events[0]['message']['type']);
        self::assertSame($response->json('data'), $events[0]['message']['data']['message']);
    }

    public function testRepeatedClientIdReturnsSameMessageWithoutNewEvent(): void
    {
        $me       = $this->actingAsUser();
        $chat     = Chat::factory()->between($me, User::factory()->create())->create();
        $clientId = Str::uuid()->toString();

        $first  = $this->postJson('/api/chats/' . $chat->id . '/messages', ['body' => 'Раз', 'client_id' => $clientId])->assertCreated();
        $second = $this->postJson('/api/chats/' . $chat->id . '/messages', ['body' => 'Раз', 'client_id' => \strtoupper($clientId)])->assertCreated();

        self::assertSame($first->json('data.id'), $second->json('data.id'));
        self::assertSame(1, ChatMessage::query()->count());
        self::assertCount(1, $this->app->make(RealtimeBus::class)->drain(10));
    }

    public function testValidatesBodyAndClientId(): void
    {
        $me   = $this->actingAsUser();
        $chat = Chat::factory()->between($me, User::factory()->create())->create();
        $url  = '/api/chats/' . $chat->id . '/messages';

        $this->postJson($url, ['body' => '   ', 'client_id' => Str::uuid()->toString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->postJson($url, ['body' => \str_repeat('а', 4001), 'client_id' => Str::uuid()->toString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->postJson($url, ['body' => 'Привет', 'client_id' => 'not-a-uuid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client_id');

        self::assertSame(0, ChatMessage::query()->count());
    }

    public function testCannotWriteToOtherPeopleChats(): void
    {
        $this->actingAsUser();
        $chat = Chat::factory()->between(User::factory()->create(), User::factory()->create())->create();

        $this->postJson('/api/chats/' . $chat->id . '/messages', ['body' => 'Привет', 'client_id' => Str::uuid()->toString()])
            ->assertNotFound();

        self::assertSame(0, ChatMessage::query()->count());
        self::assertSame([], $this->app->make(RealtimeBus::class)->drain(10));
    }

    public function testSendsFilesWithoutText(): void
    {
        $me     = $this->actingAsUser();
        $chat   = Chat::factory()->between($me, User::factory()->create())->create();
        $photo  = ChatAttachment::factory()->image()->processing()->create(['user_id' => $me->id]);
        $report = ChatAttachment::factory()->create(['user_id' => $me->id, 'original_name' => 'отчёт.pdf']);

        $response = $this->postJson('/api/chats/' . $chat->id . '/messages', [
            'client_id'      => Str::uuid()->toString(),
            'attachment_ids' => [$report->id, $photo->id],
        ])
            ->assertCreated()
            ->assertJsonPath('data.body', '')
            ->assertJsonCount(2, 'data.attachments')
            ->assertJsonPath('data.attachments.0.id', $photo->id)
            ->assertJsonPath('data.attachments.0.status', 'processing')
            ->assertJsonPath('data.attachments.0.url', null)
            ->assertJsonPath('data.attachments.1.name', 'отчёт.pdf');

        self::assertIsString($response->json('data.attachments.1.url'));
        self::assertStringStartsWith('/api/files/', $response->json('data.attachments.1.url'));
        self::assertSame($response->json('data.id'), $photo->refresh()->message_id);
        self::assertSame($response->json('data.id'), $report->refresh()->message_id);

        $events = $this->app->make(RealtimeBus::class)->drain(10);
        self::assertSame($response->json('data'), $events[0]['message']['data']['message']);

        // В истории и списке чатов вложения тоже видны
        $this->getJson('/api/chats/' . $chat->id . '/messages')->assertJsonCount(2, 'data.items.0.attachments');
    }

    public function testRejectsForeignSentOrFailedFiles(): void
    {
        $me    = $this->actingAsUser();
        $chat  = Chat::factory()->between($me, User::factory()->create())->create();
        $url   = '/api/chats/' . $chat->id . '/messages';
        $mine  = ChatAttachment::factory()->create(['user_id' => $me->id]);
        $cases = [
            ChatAttachment::factory()->create(),
            ChatAttachment::factory()->create(['user_id' => $me->id, 'status' => ChatAttachmentStatusEnum::Failed]),
            ChatAttachment::factory()->create([
                'user_id'    => $me->id,
                'message_id' => ChatMessage::factory()->inChat($chat, $me)->create()->id,
            ]),
        ];

        foreach ($cases as $attachment) {
            $this->postJson($url, ['client_id' => Str::uuid()->toString(), 'body' => 'Смотри', 'attachment_ids' => [$mine->id, $attachment->id]])
                ->assertNotFound()
                ->assertJsonPath('message', 'Файл не найден или уже отправлен.');
        }

        self::assertSame(1, ChatMessage::query()->count());
        self::assertNull($mine->refresh()->message_id);

        $this->postJson($url, ['client_id' => Str::uuid()->toString(), 'attachment_ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->postJson($url, ['client_id' => Str::uuid()->toString(), 'attachment_ids' => \range(1, 11)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachment_ids');
    }

    private function member(Chat $chat, User $user): ChatMember
    {
        return ChatMember::query()->where('chat_id', $chat->id)->where('user_id', $user->id)->firstOrFail();
    }
}
