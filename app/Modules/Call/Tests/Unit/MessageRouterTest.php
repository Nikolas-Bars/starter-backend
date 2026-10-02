<?php

declare(strict_types=1);

namespace App\Modules\Call\Tests\Unit;

use App\Modules\Call\Enums\CallStatusEnum;
use App\Modules\Call\Models\Call;
use App\Modules\Call\WebSockets\Connection;
use App\Modules\Call\WebSockets\ConnectionRegistry;
use App\Modules\Call\WebSockets\FrameCodec;
use App\Modules\Call\WebSockets\MessageRouter;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

final class MessageRouterTest extends TestCase
{
    private const OFFER = ['type' => 'offer', 'sdp' => 'v=0'];

    private ConnectionRegistry $registry;

    private MessageRouter $router;

    private FrameCodec $codec;

    private int $nextConnectionId = 1;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = $this->app->make(ConnectionRegistry::class);
        $this->router   = $this->app->make(MessageRouter::class);
        $this->codec    = new FrameCodec();
        $this->alice    = User::factory()->create(['name' => 'Алиса']);
        $this->bob      = User::factory()->create(['name' => 'Боб']);
    }

    public function testRepliesToPing(): void
    {
        $alice = $this->connect($this->alice);

        $this->send($alice, 'ping');

        self::assertSame([['type' => 'pong']], $this->messages($alice));
    }

    public function testInviteRingsEveryTabOfCallee(): void
    {
        $alice     = $this->connect($this->alice);
        $bobLaptop = $this->connect($this->bob);
        $bobPhone  = $this->connect($this->bob);

        $this->send($alice, 'call.invite', ['callee_id' => $this->bob->id]);

        $ringing = $this->messages($alice);
        self::assertSame('call.ringing', $ringing[0]['type']);
        self::assertSame('ringing', $this->dataPath($ringing[0], 'call.status'));

        foreach ([$bobLaptop, $bobPhone] as $tab) {
            $incoming = $this->messages($tab);
            self::assertSame('call.incoming', $incoming[0]['type']);
            self::assertSame('Алиса', $this->dataPath($incoming[0], 'call.caller.name'));
        }
    }

    public function testInviteToOfflineUserEndsImmediately(): void
    {
        $alice = $this->connect($this->alice);

        $this->send($alice, 'call.invite', ['callee_id' => $this->bob->id]);

        $messages = $this->messages($alice);
        self::assertSame('call.ended', $messages[0]['type']);
        self::assertSame('unavailable', $this->dataPath($messages[0], 'reason'));
    }

    public function testAcceptConnectsBothSidesAndStopsRingingInOtherTabs(): void
    {
        [$alice, $bobLaptop, $bobPhone, $callId] = $this->ringBobOnTwoTabs();

        $this->send($bobLaptop, 'call.accept', ['call_id' => $callId]);

        self::assertSame(['call.accepted'], $this->types($alice));
        self::assertSame(['call.accepted'], $this->types($bobLaptop));

        $phone = $this->messages($bobPhone);
        self::assertSame('call.ended', $phone[0]['type']);
        self::assertSame('answered_elsewhere', $this->dataPath($phone[0], 'reason'));
    }

    public function testRelaysSignalsOnlyBetweenConnectedTabs(): void
    {
        [$alice, $bobLaptop, $bobPhone, $callId] = $this->ringBobOnTwoTabs();
        $this->send($bobLaptop, 'call.accept', ['call_id' => $callId]);
        $this->drain($alice, $bobLaptop, $bobPhone);

        $this->send($alice, 'signal.offer', ['call_id' => $callId, 'payload' => self::OFFER]);

        self::assertSame([[
            'type' => 'signal.offer',
            'data' => ['call_id' => $callId, 'payload' => self::OFFER],
        ]], $this->messages($bobLaptop));
        self::assertSame([], $this->messages($bobPhone));

        $this->send($bobPhone, 'signal.answer', ['call_id' => $callId, 'payload' => self::OFFER]);

        self::assertSame(['error'], $this->types($bobPhone));
        self::assertSame([], $this->messages($alice));
    }

    public function testRejectNotifiesCallerAndAllCalleeTabs(): void
    {
        [$alice, $bobLaptop, $bobPhone, $callId] = $this->ringBobOnTwoTabs();

        $this->send($bobPhone, 'call.reject', ['call_id' => $callId]);

        foreach ([$alice, $bobLaptop, $bobPhone] as $connection) {
            $messages = $this->messages($connection);
            self::assertSame('call.ended', $messages[0]['type']);
            self::assertSame('rejected', $this->dataPath($messages[0], 'reason'));
        }
    }

    public function testHangupEndsActiveCall(): void
    {
        [$alice, $bobLaptop, $bobPhone, $callId] = $this->ringBobOnTwoTabs();
        $this->send($bobLaptop, 'call.accept', ['call_id' => $callId]);
        $this->drain($alice, $bobLaptop, $bobPhone);

        $this->send($alice, 'call.hangup', ['call_id' => $callId]);

        self::assertSame('ended', $this->dataPath($this->messages($alice)[0], 'reason'));
        self::assertSame('ended', $this->dataPath($this->messages($bobLaptop)[0], 'reason'));
        self::assertSame([], $this->messages($bobPhone));
        self::assertNull($this->registry->callOf($alice));
    }

    public function testDisconnectDuringCallWaitsForParticipantAndEndsAfterTimeout(): void
    {
        [$alice, $bobLaptop, $bobPhone, $callId] = $this->ringBobOnTwoTabs();
        $this->send($bobLaptop, 'call.accept', ['call_id' => $callId]);
        $this->drain($alice, $bobLaptop, $bobPhone);

        $this->router->disconnect($bobLaptop, now: 1000.0);
        $this->router->tick(30, now: 1019.0);

        self::assertSame([], $this->messages($alice));
        self::assertSame(CallStatusEnum::Active, Call::query()->findOrFail($callId)->status);

        $this->router->tick(30, now: 1020.0);

        self::assertSame('ended', $this->dataPath($this->messages($alice)[0], 'reason'));
        self::assertSame(CallStatusEnum::Ended, Call::query()->findOrFail($callId)->status);
    }

    public function testParticipantResumesCallFromNewConnection(): void
    {
        [$alice, $bobLaptop, $bobPhone, $callId] = $this->ringBobOnTwoTabs();
        $this->send($bobLaptop, 'call.accept', ['call_id' => $callId]);
        $this->drain($alice, $bobLaptop, $bobPhone);

        $this->router->disconnect($bobLaptop, now: 1000.0);
        $bobAgain = $this->connect($this->bob);
        $this->send($bobAgain, 'call.resume', ['call_id' => $callId]);

        self::assertSame(['call.resumed'], $this->types($bobAgain));

        $this->router->tick(30, now: 2000.0);
        self::assertSame(CallStatusEnum::Active, Call::query()->findOrFail($callId)->status);

        $this->send($alice, 'signal.offer', ['call_id' => $callId, 'payload' => self::OFFER]);
        self::assertSame(['signal.offer'], $this->types($bobAgain));
    }

    public function testResumeReplacesConnectionServerHasNotNoticedIsDead(): void
    {
        [$alice, $bobLaptop, $bobPhone, $callId] = $this->ringBobOnTwoTabs();
        $this->send($bobLaptop, 'call.accept', ['call_id' => $callId]);
        $this->drain($alice, $bobLaptop, $bobPhone);

        $bobAgain = $this->connect($this->bob);
        $this->send($bobAgain, 'call.resume', ['call_id' => $callId]);
        $this->drain($bobAgain);

        $this->router->disconnect($bobLaptop, now: 1000.0);
        $this->router->tick(30, now: 2000.0);

        self::assertSame(CallStatusEnum::Active, Call::query()->findOrFail($callId)->status);
        self::assertSame($callId, $this->registry->callOf($bobAgain));
    }

    public function testCannotResumeFinishedOrForeignCall(): void
    {
        [$alice, $bobLaptop, $bobPhone, $callId] = $this->ringBobOnTwoTabs();

        $this->send($bobPhone, 'call.resume', ['call_id' => $callId]);
        self::assertSame('call.resume', $this->dataPath($this->messages($bobPhone)[0], 'request'));

        $this->send($bobLaptop, 'call.accept', ['call_id' => $callId]);
        $this->drain($alice, $bobLaptop, $bobPhone);

        $carol = $this->connect(User::factory()->create());
        $this->send($carol, 'call.resume', ['call_id' => $callId]);
        self::assertSame(['error'], $this->types($carol));
    }

    public function testDisconnectWhileRingingEndsCallImmediately(): void
    {
        [$alice, $bobLaptop, $bobPhone, $callId] = $this->ringBobOnTwoTabs();

        $this->router->disconnect($alice);

        foreach ([$bobLaptop, $bobPhone] as $tab) {
            self::assertSame(['presence.changed', 'call.ended'], $this->types($tab));
        }
        self::assertSame(CallStatusEnum::Missed, Call::query()->findOrFail($callId)->status);
    }

    public function testGreetsWithOnlineUsersAndAnnouncesPresence(): void
    {
        $alice = $this->connect($this->alice, greet: true);

        self::assertSame(['ready', 'presence.snapshot'], $this->types($alice));

        $bobLaptop = $this->connect($this->bob, greet: true);
        $snapshot  = $this->messages($bobLaptop)[1];
        self::assertEqualsCanonicalizing([$this->alice->id, $this->bob->id], $this->dataPath($snapshot, 'user_ids'));
        self::assertSame([['type' => 'presence.changed', 'data' => ['user_id' => $this->bob->id, 'online' => true]]], $this->messages($alice));

        $bobPhone = $this->connect($this->bob, greet: true);
        $this->drain($bobLaptop, $bobPhone);
        self::assertSame([], $this->messages($alice), 'Вторая вкладка — не новость');

        $this->router->disconnect($bobPhone);
        self::assertSame([], $this->messages($alice));

        $this->router->disconnect($bobLaptop);
        self::assertSame([['type' => 'presence.changed', 'data' => ['user_id' => $this->bob->id, 'online' => false]]], $this->messages($alice));
    }

    public function testUnansweredCallBecomesMissed(): void
    {
        [$alice, $bobLaptop, $bobPhone, $callId] = $this->ringBobOnTwoTabs();
        Call::query()->whereKey($callId)->update(['started_at' => Date::now()->subMinute()]);

        $this->router->tick(30);

        foreach ([$alice, $bobLaptop, $bobPhone] as $connection) {
            self::assertSame('missed', $this->dataPath($this->messages($connection)[0], 'reason'));
        }
    }

    public function testCalleeCannotBeCalledWhileTalking(): void
    {
        [, $bobLaptop, , $callId] = $this->ringBobOnTwoTabs();
        $this->send($bobLaptop, 'call.accept', ['call_id' => $callId]);

        $carol = $this->connect(User::factory()->create());
        $this->send($carol, 'call.invite', ['callee_id' => $this->bob->id]);

        self::assertSame('busy', $this->dataPath($this->messages($carol)[0], 'reason'));
    }

    public function testGuestCanCallOnlyLinkOwnerAndOwnerCanCallBack(): void
    {
        $guestUser = User::factory()->create(['guest_of_id' => $this->bob->id]);
        $guest     = $this->connect($guestUser);
        $bob       = $this->connect($this->bob);
        $alice     = $this->connect($this->alice);

        $this->send($guest, 'call.invite', ['callee_id' => $this->alice->id]);
        self::assertSame('Нельзя позвонить этому пользователю.', $this->dataPath($this->messages($guest)[0], 'message'));
        self::assertSame([], $this->messages($alice));

        $this->send($alice, 'call.invite', ['callee_id' => $guestUser->id]);
        self::assertSame(['error'], $this->types($alice), 'Чужого гостя вызвать нельзя');

        $this->send($guest, 'call.invite', ['callee_id' => $this->bob->id]);
        self::assertSame(['call.ringing'], $this->types($guest));
        self::assertSame(true, $this->dataPath($this->messages($bob)[0], 'call.caller.is_guest'));
    }

    public function testGuestAndOwnerSeeOnlyEachOtherOnline(): void
    {
        $alice = $this->connect($this->alice, greet: true);
        $bob   = $this->connect($this->bob, greet: true);
        $this->drain($alice, $bob);

        $guest = $this->connect(User::factory()->create(['guest_of_id' => $this->bob->id]), greet: true);

        self::assertSame([$this->bob->id], $this->dataPath($this->messages($guest)[1], 'user_ids'));
        self::assertSame(['presence.changed'], $this->types($bob));
        self::assertSame([], $this->messages($alice));

        $carol = $this->connect(User::factory()->create(), greet: true);
        self::assertEqualsCanonicalizing(
            [$this->alice->id, $this->bob->id, $carol->user?->id],
            $this->dataPath($this->messages($carol)[1], 'user_ids'),
        );
        self::assertSame([], $this->messages($guest), 'Гость не узнаёт о других пользователях');
    }

    public function testRepliesWithLocalizedErrorToBadMessages(): void
    {
        $alice = $this->connect($this->alice);

        $this->router->handle($alice, 'not json');
        $this->send($alice, 'call.invite', ['callee_id' => 'bob']);
        $this->send($alice, 'call.accept', ['call_id' => 999_999]);

        $errors = $this->messages($alice);
        self::assertSame('Некорректное сообщение.', $this->dataPath($errors[0], 'message'));
        self::assertSame('call.invite', $this->dataPath($errors[1], 'request'));
        self::assertSame('Звонок не найден.', $this->dataPath($errors[2], 'message'));
    }

    /**
     * Алиса звонит Бобу, у которого открыты две вкладки.
     *
     * @return array{Connection, Connection, Connection, int}
     */
    private function ringBobOnTwoTabs(): array
    {
        $alice     = $this->connect($this->alice);
        $bobLaptop = $this->connect($this->bob);
        $bobPhone  = $this->connect($this->bob);

        $this->send($alice, 'call.invite', ['callee_id' => $this->bob->id]);

        $callId = $this->dataPath($this->messages($alice)[0], 'call.id');
        self::assertIsInt($callId);
        $this->drain($bobLaptop, $bobPhone);

        return [$alice, $bobLaptop, $bobPhone, $callId];
    }

    /**
     * @param bool $greet Как на сервере: ready, список тех, кто в сети, и оповещение остальных
     */
    private function connect(User $user, bool $greet = false): Connection
    {
        $connection           = new Connection($this->nextConnectionId++, $this->codec, 0.0);
        $connection->upgraded = true;

        $this->registry->add($connection);
        $this->registry->authenticate($connection, $user);

        if ($greet) {
            $this->router->connected($connection);
        }

        return $connection;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function send(Connection $connection, string $type, array $data = []): void
    {
        $this->router->handle($connection, (string)\json_encode(['type' => $type, 'data' => $data]));
    }

    /**
     * Разбирает всё, что сервер отправил в соединение, и очищает буфер.
     *
     * @return list<array<string, mixed>>
     */
    private function messages(Connection $connection): array
    {
        $buffer = $connection->outbound();
        $connection->consumeOutbound(\strlen($buffer));

        $messages = [];

        while (($frame = $this->codec->decode($buffer, expectMasked: false)) !== null) {
            /** @var array<string, mixed> $message */
            $message    = \json_decode($frame->payload, true, flags: JSON_THROW_ON_ERROR);
            $messages[] = $message;
        }

        return $messages;
    }

    /**
     * @return list<mixed>
     */
    private function types(Connection $connection): array
    {
        return \array_column($this->messages($connection), 'type');
    }

    private function drain(Connection ...$connections): void
    {
        foreach ($connections as $connection) {
            $this->messages($connection);
        }
    }

    /**
     * @param array<string, mixed> $message
     */
    private function dataPath(array $message, string $path): mixed
    {
        return data_get($message, 'data.' . $path);
    }
}
